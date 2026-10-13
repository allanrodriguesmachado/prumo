<?php

namespace App\Services\Telegram;

use App\Models\Household;
use App\Models\User;
use App\Services\FinanceAnalytics;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bot do Telegram (gratuito). Funciona por long-polling, então não precisa
 * de URL pública: o agendador chama poll() a cada minuto.
 *
 * Comandos: /vincular CÓDIGO, /saldo, /hoje, /resumo, "45,90 almoço", "+300 freela"
 */
class TelegramBot
{
    public function enabled(): bool
    {
        return (bool) config('prumo.telegram.token');
    }

    public function send(string $chatId, string $text): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        try {
            return Http::timeout(15)->post($this->url('sendMessage'), [
                'chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true,
            ])->ok();
        } catch (\Throwable $e) {
            Log::warning('telegram send: '.$e->getMessage());

            return false;
        }
    }

    public function poll(): int
    {
        if (! $this->enabled()) {
            return 0;
        }
        $offset = (int) Cache::get('telegram:offset', 0);
        $res = Http::timeout(30)->get($this->url('getUpdates'), ['offset' => $offset, 'timeout' => 20, 'allowed_updates' => json_encode(['message'])]);
        $updates = $res->json('result') ?? [];
        foreach ($updates as $u) {
            Cache::forever('telegram:offset', $u['update_id'] + 1);
            $msg = $u['message'] ?? null;
            if (! $msg || empty($msg['text'])) {
                continue;
            }
            try {
                $this->handle((string) $msg['chat']['id'], trim($msg['text']));
            } catch (\Throwable $e) {
                Log::error('telegram handle: '.$e->getMessage());
                $this->send((string) $msg['chat']['id'], 'Ops, não consegui processar sua mensagem. 😕');
            }
        }

        return count($updates);
    }

    public function handle(string $chatId, string $text): void
    {
        if (preg_match('/^\/(?:start|vincular)\s+([A-Z0-9]{6,})/i', $text, $m)) {
            $user = User::where('telegram_link_code', strtoupper($m[1]))->first();
            if (! $user) {
                $this->send($chatId, 'Código inválido. Gere um novo em <b>Perfil → Telegram</b> no Prumo.');

                return;
            }
            $user->forceFill(['telegram_chat_id' => $chatId, 'telegram_link_code' => null, 'notify_telegram' => true])->save();
            $this->send($chatId, "✅ Pronto, {$user->firstName()}! Seu Telegram está conectado ao Prumo.\n\n".$this->help());

            return;
        }

        $user = User::where('telegram_chat_id', $chatId)->first();
        if (! $user) {
            $this->send($chatId, "Olá! Para conectar, abra o Prumo em <b>Perfil → Telegram</b> e envie aqui:\n<code>/vincular SEUCODIGO</code>");

            return;
        }

        Auth::setUser($user);
        $user->load('currentHousehold');
        $analytics = new FinanceAnalytics($user->currentHousehold);
        $cmd = strtolower(strtok($text, ' '));

        match (true) {
            in_array($cmd, ['/saldo', 'saldo']) => $this->send($chatId, '💰 Saldo em contas: <b>'.brl($analytics->totalBalance()).'</b>'),
            in_array($cmd, ['/hoje', 'hoje']) => $this->sendToday($chatId, $analytics),
            in_array($cmd, ['/resumo', 'resumo']) => $this->sendSummary($chatId, $analytics),
            in_array($cmd, ['/ajuda', '/help', '/start', 'ajuda']) => $this->send($chatId, $this->help()),
            default => $this->quickAdd($chatId, $text, $analytics),
        };
    }

    private function quickAdd(string $chatId, string $text, FinanceAnalytics $analytics): void
    {
        if (! preg_match('/^([+-]?)\s*(?:r\$\s*)?(\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?|\d+(?:[.,]\d{1,2})?)\s+(.+)$/iu', $text, $m)) {
            $this->send($chatId, "Não entendi. 🤔\n\n".$this->help());

            return;
        }
        $income = $m[1] === '+';
        $amount = parse_money($m[2]);
        $description = mb_substr(trim($m[3]), 0, 120);
        $account = \App\Models\Account::where('archived', false)->orderByRaw("case when type='wallet' then 1 when type='checking' then 0 else 2 end")->first();

        [$t] = app(TransactionService::class)->create([
            'type' => $income ? 'income' : 'expense',
            'kind' => $income ? 'extra_income' : 'daily',
            'description' => ucfirst($description),
            'amount' => $amount,
            'date' => now()->toDateString(),
            'paid' => true,
            'account_id' => $account?->id,
            'source' => 'telegram',
        ]);
        $t->load('category');
        $sts = $analytics->safeToSpend();
        $this->send($chatId, ($income ? '🟢 Receita' : '🔴 Gasto').' registrado: <b>'.brl($amount)."</b> — {$t->description}\n"
            .'Categoria: '.($t->category ? "{$t->category->icon} {$t->category->name}" : 'sem categoria')."\n"
            .($income ? '' : 'Ainda pode gastar hoje: <b>'.brl($sts['today_left']).'</b>'));
    }

    private function sendToday(string $chatId, FinanceAnalytics $a): void
    {
        $s = $a->safeToSpend();
        $this->send($chatId, $s['negative']
            ? '⚠️ O mês já está comprometido além da renda. Segure gastos não essenciais.'
            : '📅 Você pode gastar <b>'.brl($s['per_day'])."/dia</b> até o fim do mês.\nHoje já foram ".brl($s['spent_today']).', restam <b>'.brl($s['today_left']).'</b>.');
    }

    private function sendSummary(string $chatId, FinanceAnalytics $a): void
    {
        $m = $a->monthSummary(now());
        $score = $a->score();
        $this->send($chatId, '📊 <b>'.ucfirst(month_name(now())).'</b>'
            ."\nReceitas: ".brl($m['income'])
            ."\nDespesas: ".brl($m['expense'])
            ."\nResultado: <b>".brl($m['balance']).'</b>'
            ."\nPrumo Score: <b>{$score['total']}</b> ({$score['level']})");
    }

    private function help(): string
    {
        return "Como usar:\n• <code>32,50 almoço</code> — registra um gasto\n• <code>+150 freela</code> — registra uma receita\n• /hoje — quanto posso gastar hoje\n• /saldo — saldo das contas\n• /resumo — resumo do mês";
    }

    private function url(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('prumo.telegram.token').'/'.$method;
    }
}
