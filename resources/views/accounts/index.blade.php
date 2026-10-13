@php
    $isCouple = household()->isCouple();
    $active = $accounts->where('archived', false);
    $total = $active->sum(fn ($a) => $balances[$a->id] ?? 0);
    $totalProjected = $active->sum(fn ($a) => $projected[$a->id] ?? 0);
@endphp
<x-layouts.app title="Contas">
    <x-page-header title="Contas" subtitle="Bancos, carteira, poupança e vale-refeição — tudo num lugar só.">
        <x-slot:actions><a href="{{ route('accounts.create') }}" class="btn-primary">+ Nova conta</a></x-slot:actions>
    </x-page-header>

    @if($accounts->isEmpty())
        <div class="card">
            <x-empty icon="🏦" title="Nenhuma conta cadastrada" text="Comece pela conta onde seu salário cai. Você pode adicionar a carteira (dinheiro vivo) e o vale-refeição também.">
                <a href="{{ route('accounts.create') }}" class="btn-primary">+ Cadastrar primeira conta</a>
            </x-empty>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="card card-pad hero text-white">
                <div class="text-[13px] font-medium text-brand-200">Saldo total hoje</div>
                <div class="mt-2 font-display text-3xl font-bold tracking-tight tabular {{ $total < 0 ? 'text-rose-300' : '' }}">{{ brl($total) }}</div>
                <div class="mt-1 text-xs text-brand-200/80">Somando {{ $active->count() }} {{ $active->count() === 1 ? 'conta ativa' : 'contas ativas' }}</div>
            </div>
            <x-stat label="Previsto para o fim do mês" :value="brl($totalProjected)" :tone="$totalProjected < 0 ? 'red' : 'brand'" icon="🔮"
                    :hint="'Considerando os lançamentos já pagos até '.now()->endOfMonth()->format('d/m').'. '.($totalProjected < $total ? 'Fique de olho nas contas a pagar.' : 'Bom sinal! 🎉')" />
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 @4xl:grid-cols-3">
            @foreach($accounts as $a)
                @php $bal = $balances[$a->id] ?? 0; $proj = $projected[$a->id] ?? 0; @endphp
                <div class="card relative overflow-hidden {{ $a->archived ? 'opacity-60' : '' }}">
                    <div class="h-1.5" style="background-color: {{ $a->color ?? '#0f766e' }}"></div>
                    <div class="card-pad">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-sm font-bold text-white" style="background-color: {{ $a->color ?? '#0f766e' }}">
                                    {{ mb_strtoupper(mb_substr($a->name, 0, 2)) }}
                                </span>
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-slate-900">{{ $a->name }}</div>
                                    <div class="truncate text-xs text-slate-500">
                                        {{ $a->bank_name ?: (\App\Models\Account::TYPES[$a->type] ?? $a->type) }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-0.5">
                                <a href="{{ route('accounts.edit', $a) }}" class="btn-ghost btn-sm" title="Editar">✏️</a>
                                <form method="POST" action="{{ route('accounts.destroy', $a) }}" onsubmit="return confirm('Excluir esta conta? Se ela tiver lançamentos, será apenas arquivada.')">
                                    @csrf @method('DELETE')
                                    <button class="btn-ghost btn-sm text-rose-600" title="Excluir">🗑️</button>
                                </form>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <span class="badge-slate">{{ \App\Models\Account::TYPES[$a->type] ?? $a->type }}</span>
                            @if($isCouple && $a->is_shared)<span class="badge-violet">💑 Compartilhada</span>@endif
                            @if($isCouple && ! $a->is_shared && $a->user)<span class="badge-sky">{{ $a->user->name }}</span>@endif
                            @if($a->archived)<span class="badge-amber">Arquivada</span>@endif
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4">
                            <div>
                                <div class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Saldo atual</div>
                                <div class="mt-0.5 text-lg font-bold tabular {{ $bal < 0 ? 'money-neg' : 'text-slate-900' }}">{{ brl($bal) }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Fim do mês</div>
                                <div class="mt-0.5 text-lg font-semibold tabular {{ $proj < 0 ? 'money-neg' : 'text-slate-600' }}">{{ brl($proj) }}</div>
                            </div>
                        </div>

                        <a href="{{ route('accounts.show', $a) }}" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:underline">
                            Ver extrato e conciliar →
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-6 text-center text-xs text-slate-400">Cartões de crédito ficam em <a href="{{ route('cards.index') }}" class="font-medium text-brand-700 hover:underline">Cartões</a> — compras no cartão só saem da conta quando a fatura é paga.</p>
    @endif
</x-layouts.app>
