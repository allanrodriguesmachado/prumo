@php $couple = household()->isCouple(); @endphp
<x-layouts.app title="Lançamentos">
    <x-page-header title="Lançamentos" subtitle="Tudo que entra e sai: salário, renda extra, gastos fixos, parcelas e o dia a dia.">
        <x-slot:actions>
            <x-month-nav :month="$month" route="transactions.index" :params="array_filter($filters)" />
            <a href="{{ route('transactions.export', array_merge($filters, ['mes' => $month->format('Y-m')])) }}" class="btn-secondary" title="Exportar CSV">⬇️ <span class="hidden sm:inline">CSV</span></a>
            <a href="{{ route('transactions.create') }}" class="btn-primary">+ Novo</a>
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid sm:grid-cols-3">
        <x-stat label="Entradas (filtro)" :value="brl($totals['income'])" tone="green" />
        <x-stat label="Saídas (filtro)" :value="brl($totals['expense'])" tone="red" />
        <x-stat label="Resultado" :value="brl($totals['income'] - $totals['expense'])" :tone="$totals['income'] - $totals['expense'] >= 0 ? 'brand' : 'red'" />
    </div>

    {{-- Filtros --}}
    <form method="GET" class="card mt-4 p-4" x-data="{ more: {{ count(array_filter($filters)) > 1 ? 'true' : 'false' }} }">
        <input type="hidden" name="mes" value="{{ $month->format('Y-m') }}">
        <div class="flex flex-wrap items-center gap-2">
            <input name="busca" value="{{ $filters['busca'] ?? '' }}" placeholder="🔎 Buscar descrição..." class="input max-w-xs flex-1">
            <div class="flex rounded-xl border border-slate-200 p-0.5 text-sm">
                @foreach(['' => 'Todos', 'income' => 'Receitas', 'expense' => 'Despesas', 'transfer' => 'Transf.'] as $v => $l)
                    <label class="cursor-pointer rounded-lg px-3 py-1.5 {{ ($filters['tipo'] ?? '') === $v ? 'bg-brand-700 text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                        <input type="radio" name="tipo" value="{{ $v }}" class="sr-only" onchange="this.form.submit()" @checked(($filters['tipo'] ?? '') === $v)>{{ $l }}
                    </label>
                @endforeach
            </div>
            <button type="button" class="btn-ghost btn-sm" @click="more = !more">Mais filtros</button>
            <button class="btn-secondary btn-sm">Filtrar</button>
            @if(count(array_filter($filters)))<a href="{{ route('transactions.index', ['mes' => $month->format('Y-m')]) }}" class="text-xs font-semibold text-slate-500 hover:underline">limpar</a>@endif
        </div>
        <div x-cloak x-show="more" x-collapse class="mt-3 grid gap-2 sm:grid-cols-2 @4xl:grid-cols-5">
            <select name="natureza" class="input"><option value="">Natureza</option>@foreach(\App\Models\Transaction::KINDS as $k => $l)<option value="{{ $k }}" @selected(($filters['natureza'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
            <select name="categoria" class="input"><option value="">Categoria</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(($filters['categoria'] ?? '') == $c->id)>{{ $c->icon }} {{ $c->name }}</option>@endforeach</select>
            <select name="conta" class="input"><option value="">Conta</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected(($filters['conta'] ?? '') == $a->id)>{{ $a->name }}</option>@endforeach</select>
            <select name="cartao" class="input"><option value="">Cartão</option>@foreach($cards as $c)<option value="{{ $c->id }}" @selected(($filters['cartao'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach</select>
            <select name="status" class="input"><option value="">Status</option><option value="pago" @selected(($filters['status'] ?? '') === 'pago')>Pago/recebido</option><option value="pendente" @selected(($filters['status'] ?? '') === 'pendente')>Pendente</option></select>
            @if($couple)
                <select name="pessoa" class="input"><option value="">Pessoa</option><option value="shared" @selected(($filters['pessoa'] ?? '') === 'shared')>💑 Compartilhados</option>@foreach($members as $m)<option value="{{ $m->id }}" @selected(($filters['pessoa'] ?? '') == $m->id)>{{ $m->firstName() }}</option>@endforeach</select>
            @endif
        </div>
    </form>

    {{-- Lista --}}
    <div class="card mt-4 overflow-hidden">
        @php $grouped = $transactions->getCollection()->groupBy(fn ($t) => $t->date->toDateString()); @endphp
        @forelse($grouped as $date => $items)
            @php $d = \Carbon\Carbon::parse($date); $dayTotal = $items->sum(fn ($t) => $t->type === 'income' ? $t->amount : ($t->type === 'expense' && $t->kind !== 'invoice_payment' ? -$t->amount : 0)); @endphp
            <div class="flex items-center justify-between bg-slate-50/80 px-5 py-2 text-xs font-semibold text-slate-500">
                <span class="capitalize">{{ $d->isToday() ? 'Hoje' : ($d->isYesterday() ? 'Ontem' : $d->translatedFormat('l, d \d\e F')) }}</span>
                <span class="tabular {{ $dayTotal >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ brl($dayTotal, true) }}</span>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach($items as $t)
                    <li class="group flex items-center gap-3 px-5 py-3 hover:bg-slate-50/60">
                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-lg" style="background: {{ ($t->category?->color ?? '#94a3b8') }}1a">{{ $t->category?->icon ?? ($t->type === 'transfer' ? '🔁' : '•') }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <a href="{{ route('transactions.edit', $t) }}" class="truncate text-sm font-medium text-slate-800 hover:underline">{{ $t->label() }}</a>
                                @if(! $t->paid && ! $t->credit_card_id)<span class="badge-amber">{{ $t->type === 'income' ? 'a receber' : 'a pagar' }}</span>@endif
                                @if($t->is_shared && $couple)<span class="badge-violet">💑 casal</span>@endif
                                @if($t->approval_status === 'pending')<a href="{{ route('couple.index') }}#aprovacoes" class="badge-amber">⏳ aguardando aprovação</a>@endif
                                @if($t->approval_status === 'rejected')<span class="badge-slate" title="O(a) parceiro(a) recusou dividir">recusada no casal</span>@endif
                                @if($t->is_private)<span class="badge-slate">🔒 privado</span>@endif
                                @if(in_array($t->kind, ['fixed', 'salary']))<span class="badge-slate">{{ \App\Models\Transaction::KINDS[$t->kind] }}</span>@endif
                                @if($t->source === 'telegram')<span class="badge-sky">telegram</span>@endif
                            </div>
                            <div class="mt-0.5 truncate text-xs text-slate-500">
                                {{ $t->category?->name ?? 'Sem categoria' }} ·
                                @if($t->credit_card_id) 💳 {{ $t->creditCard?->name }} (fatura {{ $t->invoice_month ? month_name($t->invoice_month, true) : '' }})
                                @elseif($t->type === 'transfer') {{ $t->account?->name }} → {{ $t->transferAccount?->name }}
                                @else {{ $t->account?->name ?? 'sem conta' }} @endif
                                @if($couple) · {{ $t->user?->firstName() }} @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="tabular text-sm font-semibold {{ $t->type === 'income' ? 'money-pos' : ($t->type === 'transfer' ? 'text-slate-500' : 'text-slate-800') }}">{{ $t->type === 'income' ? '+' : ($t->type === 'transfer' ? '' : '-') }}{{ brl($t->amount) }}</div>
                            @if($t->type === 'expense' && $hourly > 0 && $t->kind !== 'invoice_payment')<div class="text-[11px] text-slate-400" title="Horas de trabalho equivalentes">⏱️ {{ num($t->amount / $hourly, 1) }}h</div>@endif
                        </div>
                        <div class="flex shrink-0 items-center gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100">
                            @if(! $t->credit_card_id && $t->type !== 'transfer')
                                <form method="POST" action="{{ route('transactions.toggle', $t) }}">@csrf @method('PATCH')
                                    <button class="rounded-lg p-1.5 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600" title="{{ $t->paid ? 'Marcar como pendente' : 'Marcar como pago' }}">{{ $t->paid ? '✅' : '⭕' }}</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('transactions.destroy', $t) }}" onsubmit="return confirm('Excluir este lançamento?{{ $t->installment_group ? ' (apenas esta parcela)' : '' }}')">@csrf @method('DELETE')
                                <button class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Excluir">🗑️</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @empty
            <x-empty icon="🗂️" title="Nenhum lançamento encontrado" text="Mude o mês ou os filtros, ou faça seu primeiro lançamento.">
                <div class="flex gap-2"><a href="{{ route('transactions.create') }}" class="btn-primary btn-sm">Novo lançamento</a><a href="{{ route('imports.index') }}" class="btn-secondary btn-sm">Importar extrato</a></div>
            </x-empty>
        @endforelse
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>
</x-layouts.app>
