<x-layouts.app :title="'Extrato · '.$account->name">
    <x-page-header :title="'Extrato — '.$account->name" :subtitle="($account->bank_name ? $account->bank_name.' · ' : '').(\App\Models\Account::TYPES[$account->type] ?? '')">
        <x-slot:actions>
            <x-month-nav :month="$month" route="accounts.show" :params="['account' => $account->id]" />
            <a href="{{ route('imports.index') }}" class="btn-secondary">Importar extrato</a>
            <a href="{{ route('accounts.edit', $account) }}" class="btn-ghost">Editar</a>
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid @3xl:grid-cols-4">
        <x-stat label="Saldo de abertura" :value="brl($opening)" :hint="'em '.$month->copy()->startOfMonth()->format('d/m')" />
        <x-stat label="Saldo de fechamento previsto" :value="brl($closing)" :tone="$closing < 0 ? 'red' : 'brand'" :hint="'em '.$month->copy()->endOfMonth()->format('d/m').' (inclui pendentes)'" />
        <x-stat label="Saldo realizado hoje" :value="brl($current)" icon="🏦" hint="só lançamentos pagos/recebidos" />
        <x-stat label="Não conferidos" :value="$unreconciled" icon="🔍" hint="pagos, mas ainda não batidos com o extrato do banco" />
    </div>

    <div class="card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Data</th><th>Descrição</th><th class="text-right">Valor</th><th class="text-right">Saldo</th><th class="text-center">Conferido</th></tr></thead>
                <tbody>
                    <tr class="bg-slate-50/60"><td colspan="3" class="text-xs font-semibold text-slate-500">Saldo anterior</td><td class="text-right tabular text-xs font-semibold">{{ brl($opening) }}</td><td></td></tr>
                    @forelse($rows as $r)
                        @php $t = $r['t']; @endphp
                        <tr class="{{ $t->paid ? '' : 'text-slate-400' }}">
                            <td class="whitespace-nowrap tabular">{{ $t->date->format('d/m') }}</td>
                            <td>
                                <a href="{{ route('transactions.edit', $t) }}" class="hover:underline">{{ $t->category?->icon ?? ($t->type === 'transfer' ? '🔁' : '•') }} {{ $t->label() }}</a>
                                @unless($t->paid)<span class="badge-amber ml-1">pendente</span>@endunless
                            </td>
                            <td class="text-right tabular font-medium {{ $r['value'] >= 0 ? 'money-pos' : 'text-slate-800' }}">{{ brl($r['value'], true) }}</td>
                            <td class="text-right tabular {{ $r['balance'] < 0 ? 'font-semibold text-rose-600' : '' }}">{{ brl($r['balance']) }}</td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('accounts.reconcile', [$account, $t]) }}">@csrf @method('PATCH')
                                    <button class="rounded-lg p-1 text-lg hover:bg-slate-100" title="{{ $t->reconciled_at ? 'Conferido em '.$t->reconciled_at->format('d/m/Y') : 'Marcar como conferido com o banco' }}" aria-label="{{ $t->reconciled_at ? 'Desmarcar conferência' : 'Marcar como conferido' }}">{{ $t->reconciled_at ? '✔️' : '◻️' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty icon="📄" title="Sem movimentações neste mês" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-3 text-xs text-slate-500">Conciliação: compare com o extrato do banco e marque ✔️. Ao importar um OFX/CSV/XLSX, o Prumo concilia automaticamente os lançamentos que já existiam (mesmo valor, até 3 dias de diferença).</p>
</x-layouts.app>
