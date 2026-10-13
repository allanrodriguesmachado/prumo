<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\Transaction;
use App\Services\Import\Categorizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    public function __construct(private Categorizer $categorizer) {}

    /**
     * Cria um lançamento. Trata parcelamento, cartão de crédito (fatura),
     * pagamento de dívida e aporte em meta.
     *
     * @return Transaction[] lançamentos criados
     */
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $date = Carbon::parse($data['date']);
            $card = ! empty($data['credit_card_id']) ? CreditCard::findOrFail($data['credit_card_id']) : null;
            $account = $this->visibleAccount($data['account_id'] ?? null);
            $this->visibleAccount($data['transfer_account_id'] ?? null);
            $private = (bool) ($data['is_private'] ?? false) || $card?->is_private || $account?->is_private;
            $installments = max(1, (int) ($data['installments'] ?? 1));
            $amount = round((float) $data['amount'], 2);
            if ($installments > 1 && ($data['installment_value_mode'] ?? 'total') === 'each') {
                $amount = \App\Support\Money::fromCents(\App\Support\Money::toCents($amount) * $installments);
            }

            if (empty($data['category_id']) && ($data['type'] ?? '') !== 'transfer') {
                $data['category_id'] = $this->categorizer->guess($data['description'], $data['type'])?->id;
            }

            $base = [
                'type' => $data['type'],
                'kind' => $data['kind'] ?? ($data['type'] === 'income' ? 'extra_income' : 'daily'),
                'description' => trim($data['description']),
                'category_id' => $data['category_id'] ?? null,
                'account_id' => $card ? null : ($data['account_id'] ?? null),
                'transfer_account_id' => $data['transfer_account_id'] ?? null,
                'credit_card_id' => $card?->id,
                'debt_id' => $data['debt_id'] ?? null,
                'goal_id' => $data['goal_id'] ?? null,
                'is_shared' => ! $private && (bool) ($data['is_shared'] ?? false),
                'is_private' => $private,
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? 'manual',
                'import_hash' => $data['import_hash'] ?? null,
                'recurrence_id' => $data['recurrence_id'] ?? null,
            ];
            if (! empty($data['user_id'])) {
                $base['user_id'] = $data['user_id'];
            }
            if (! empty($data['household_id'])) {
                $base['household_id'] = $data['household_id'];
            }

            $created = [];
            $group = $installments > 1 ? (string) Str::uuid() : null;
            $parts = \App\Support\Money::split($amount, $installments);
            $firstInvoice = $card?->invoiceMonthFor($date);

            for ($i = 0; $i < $installments; $i++) {
                $d = $date->copy()->addMonthsNoOverflow($i);
                $paid = $card ? false : ($i === 0 ? (bool) ($data['paid'] ?? true) : false);
                if (! $card && $d->isFuture() && $i > 0) {
                    $paid = false;
                }
                $created[] = Transaction::create($base + [
                    'kind' => $installments > 1 ? 'installment' : $base['kind'],
                    'amount' => $parts[$i],
                    'date' => $d->toDateString(),
                    'paid' => $paid,
                    'invoice_month' => $firstInvoice?->copy()->addMonthsNoOverflow($i)->toDateString(),
                    'installment_group' => $group,
                    'installment_number' => $group ? $i + 1 : null,
                    'installment_total' => $group ? $installments : null,
                ]);
            }

            $this->requestApprovalIfNeeded($created, $amount);
            $this->applySideEffects($created[0], +1);
            if (! empty($data['category_id']) && ($data['source'] ?? 'manual') === 'manual') {
                $this->categorizer->learn($data['description'], (int) $data['category_id']);
            }

            return $created;
        });
    }

    public function update(Transaction $t, array $data): Transaction
    {
        return DB::transaction(function () use ($t, $data) {
            $this->applySideEffects($t, -1);
            $card = ! empty($data['credit_card_id']) ? CreditCard::findOrFail($data['credit_card_id']) : null;
            $account = $this->visibleAccount($data['account_id'] ?? null);
            $private = (bool) ($data['is_private'] ?? false) || $card?->is_private || $account?->is_private;
            $t->fill([
                'description' => $data['description'],
                'amount' => round((float) $data['amount'], 2),
                'date' => $data['date'],
                'category_id' => $data['category_id'] ?? null,
                'account_id' => $card ? null : ($data['account_id'] ?? null),
                'credit_card_id' => $card?->id,
                'invoice_month' => $card ? ($t->credit_card_id === $card->id && $t->installment_group ? $t->invoice_month : $card->invoiceMonthFor(Carbon::parse($data['date']))) : null,
                'paid' => $card ? $t->paid : (bool) ($data['paid'] ?? false),
                'is_shared' => ! $private && (bool) ($data['is_shared'] ?? false),
                'is_private' => $private,
                'notes' => $data['notes'] ?? null,
                'kind' => $data['kind'] ?? $t->kind,
            ])->save();
            $this->applySideEffects($t, +1);
            if ($t->category_id && $t->wasChanged('category_id')) {
                $this->categorizer->learn($t->description, $t->category_id);
            }

            return $t;
        });
    }

    public function delete(Transaction $t, bool $wholeGroup = false): void
    {
        DB::transaction(function () use ($t, $wholeGroup) {
            $items = $wholeGroup && $t->installment_group
                ? Transaction::where('installment_group', $t->installment_group)->get()
                : collect([$t]);
            $first = $items->sortBy('installment_number')->first();
            $this->applySideEffects($first, -1);
            $items->each->delete();
        });
    }

    /**
     * Paga a fatura do cartão (total ou parcial).
     * Parcial: a fatura é encerrada e o saldo que faltou vira um lançamento na
     * fatura seguinte (é o que o banco faz — e onde entram os juros do rotativo).
     */
    public function payInvoice(CreditCard $card, Carbon $invoiceMonth, Account $account, ?float $amount = null): Transaction
    {
        return DB::transaction(function () use ($card, $invoiceMonth, $account, $amount) {
            $invoiceMonth = $invoiceMonth->copy()->startOfMonth();
            $remaining = $card->invoiceRemaining($invoiceMonth);
            $amount = round(min($amount ?? $remaining, $remaining), 2);
            if ($amount <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'Esta fatura não tem saldo a pagar.']);
            }
            $partial = $amount < $remaining;
            $label = month_name($invoiceMonth, true).'/'.$invoiceMonth->format('y');

            $tx = Transaction::create([
                'type' => 'expense', 'kind' => 'invoice_payment',
                'description' => ($partial ? 'Pagamento parcial fatura ' : 'Fatura ')."{$card->name} {$label}",
                'amount' => $amount, 'date' => now()->toDateString(), 'paid' => true, 'account_id' => $account->id,
                'settles_card_id' => $card->id, 'invoice_month' => $invoiceMonth->toDateString(),
                'is_shared' => $card->is_shared, 'is_private' => $card->is_private,
            ]);
            Transaction::where('credit_card_id', $card->id)->whereDate('invoice_month', $invoiceMonth)->update(['paid' => true]);

            if ($partial) {
                $next = $invoiceMonth->copy()->addMonthNoOverflow();
                Transaction::create([
                    'type' => 'expense', 'kind' => 'invoice_carry',
                    'description' => "Saldo não pago da fatura {$label} (rotativo)",
                    'amount' => round($remaining - $amount, 2), 'date' => $card->dueDateFor($invoiceMonth)->toDateString(),
                    'paid' => false, 'credit_card_id' => $card->id, 'invoice_month' => $next->toDateString(),
                    'category_id' => \App\Models\Category::where('name', 'Dívidas e juros')->value('id'),
                    'is_shared' => $card->is_shared, 'is_private' => $card->is_private, 'source' => 'manual',
                ]);
            }

            return $tx;
        });
    }

    /**
     * Antecipa parcelas futuras de uma compra para a fatura atual do cartão,
     * opcionalmente com desconto (bancos costumam dar desconto dos juros embutidos).
     */
    public function anticipateInstallments(Transaction $t, int $count, float $discount = 0): int
    {
        abort_unless($t->installment_group && $t->credit_card_id, 422, 'Só é possível antecipar parcelas de compras no cartão.');

        return DB::transaction(function () use ($t, $count, $discount) {
            $card = CreditCard::findOrFail($t->credit_card_id);
            $target = $card->invoiceMonthFor(now());
            $future = Transaction::where('installment_group', $t->installment_group)->where('paid', false)
                ->whereDate('invoice_month', '>', $target->toDateString())->orderByDesc('installment_number')->take($count)->get();
            if ($future->isEmpty()) {
                return 0;
            }
            $total = (float) $future->sum('amount');
            $discount = min(max(0, $discount), $total);
            foreach ($future as $i => $p) {
                $share = $total > 0 ? round($discount * (float) $p->amount / $total, 2) : 0;
                $p->update([
                    'invoice_month' => $target->toDateString(),
                    'amount' => round((float) $p->amount - $share, 2),
                    'notes' => 'Parcela antecipada em '.now()->format('d/m/Y').($share > 0 ? ' com desconto de '.brl($share) : ''),
                ]);
            }

            return $future->count();
        });
    }

    /** Despesa compartilhada acima do limite combinado fica pendente até o(a) parceiro(a) aprovar. */
    private function requestApprovalIfNeeded(array $created, float $total): void
    {
        $first = $created[0];
        $household = \App\Models\Household::find($first->household_id);
        $threshold = $household?->approvalThreshold() ?? 0;
        if ($first->type !== 'expense' || ! $first->is_shared || $threshold <= 0 || $total < $threshold || $first->source === 'recurrence') {
            return;
        }
        Transaction::whereIn('id', collect($created)->pluck('id'))->update(['approval_status' => 'pending']);
        $partner = $household->members()->where('users.id', '!=', $first->user_id)->first();
        $partner?->notify(new \App\Notifications\ApprovalRequestedNotification($first->fresh(), $total));
    }

    /** Aprova ou recusa uma despesa compartilhada (todas as parcelas do grupo). Recusada vira despesa individual. */
    public function decideApproval(Transaction $t, \App\Models\User $decider, bool $approve): void
    {
        if ($t->approval_status !== 'pending' || $t->user_id === $decider->id) {
            throw \Illuminate\Validation\ValidationException::withMessages(['approval' => 'Só o(a) parceiro(a) pode decidir sobre esta despesa.']);
        }
        $q = $t->installment_group ? Transaction::where('installment_group', $t->installment_group) : Transaction::whereKey($t->id);
        $q->update([
            'approval_status' => $approve ? 'approved' : 'rejected',
            'is_shared' => $approve,
            'approved_by' => $decider->id,
            'approved_at' => now(),
        ]);
        \App\Models\AuditLog::record($approve ? 'shared_expense_approved' : 'shared_expense_rejected', $t);
    }

    /** Garante que a conta existe e é visível para o usuário (inclui privacidade do casal). */
    private function visibleAccount(mixed $id): ?Account
    {
        if (empty($id)) {
            return null;
        }
        $account = Account::find($id);
        if (! $account && \Illuminate\Support\Facades\Auth::check()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['account_id' => 'Conta inválida.']);
        }

        return $account;
    }

    private function applySideEffects(Transaction $t, int $direction): void
    {
        $value = (float) ($t->installment_total ? Transaction::where('installment_group', $t->installment_group)->sum('amount') : $t->amount);
        if ($t->debt_id && ($debt = Debt::find($t->debt_id))) {
            $debt->current_balance = max(0, (float) $debt->current_balance - $direction * $value);
            $debt->status = $debt->current_balance <= 0 ? 'paid' : ($debt->status === 'paid' ? 'active' : $debt->status);
            $debt->paid_off_at = $debt->status === 'paid' ? ($debt->paid_off_at ?? now()) : null;
            $debt->save();
        }
        if ($t->goal_id && ($goal = Goal::find($t->goal_id))) {
            $goal->current_amount = max(0, (float) $goal->current_amount + $direction * $value);
            $goal->achieved_at = $goal->current_amount >= $goal->target_amount ? ($goal->achieved_at ?? now()) : null;
            $goal->save();
        }
    }
}
