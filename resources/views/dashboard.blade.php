@php
    $user = auth()->user();
    $hour = now()->hour;
    $greet = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');
    $scoreColors = ['emerald' => '#10b981', 'teal' => '#14b8a6', 'amber' => '#f59e0b', 'orange' => '#f97316', 'rose' => '#f43f5e'];
    $scoreColor = $scoreColors[$score['color']] ?? '#f53003';
    $levelStyles = ['danger' => 'border-rose-200 bg-rose-50/60', 'warning' => 'border-amber-200 bg-amber-50/60', 'tip' => 'border-sky-200 bg-sky-50/60', 'success' => 'border-emerald-200 bg-emerald-50/60'];
@endphp
<x-layouts.app title="Painel">
    {{-- Saudação + indicadores --}}
    <div class="mb-5 flex flex-col gap-3 sm:mb-6 @4xl:flex-row @4xl:items-end @4xl:justify-between">
        <div>
            <p class="text-sm font-medium capitalize text-slate-500">{{ now()->translatedFormat('l') }}, {{ now()->day }} de {{ month_name(now()) }}</p>
            <h2 class="font-display text-[1.6rem] font-semibold tracking-tight text-slate-900 sm:text-3xl">{{ $greet }}, {{ $user->firstName() }}! 👋</h2>
        </div>
        <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 text-xs sm:mx-0 sm:flex-wrap sm:px-0 sm:pb-0">
            @foreach([['Selic', $indicators['selic']['value'] ?? null, '% a.a.'], ['CDI', $indicators['cdi']['value'] ?? null, '% a.a.'], ['IPCA 12m', $indicators['ipca_12m']['value'] ?? null, '%']] as [$l, $v, $s])
                @if($v !== null)<span class="shrink-0 whitespace-nowrap rounded-full bg-white px-3 py-1.5 font-medium text-slate-600 ring-1 ring-slate-200">{{ $l }} <b class="text-slate-900">{{ num($v, 2) }}{{ $s }}</b></span>@endif
            @endforeach
            @foreach(['USDBRL' => 'US$', 'EURBRL' => '€'] as $k => $sym)
                @if(isset($fx[$k]))<span class="shrink-0 whitespace-nowrap rounded-full bg-white px-3 py-1.5 font-medium text-slate-600 ring-1 ring-slate-200">{{ $sym }} <b class="text-slate-900">{{ num($fx[$k]['bid'], 2) }}</b> <span class="{{ $fx[$k]['change'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $fx[$k]['change'] >= 0 ? '▲' : '▼' }}{{ num(abs($fx[$k]['change']), 2) }}%</span></span>@endif
            @endforeach
        </div>
    </div>

    @if(! $user->onboarded_at && $accounts->count() <= 1)
        <div class="mb-6 flex flex-col gap-4 rounded-2xl hero-red p-6 text-white sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="font-display text-lg font-semibold">Complete seu mapa financeiro em 2 minutos</div>
                <p class="text-sm text-brand-100">Informe salário, gastos fixos e conta principal para liberar o "quanto posso gastar hoje".</p>
            </div>
            <a href="{{ route('setup') }}" class="btn bg-white text-brand-800 hover:bg-brand-50">Começar →</a>
        </div>
    @endif

    {{-- Linha 1: hoje, score, patrimônio --}}
    <div class="grid gap-4 @2xl:grid-cols-2 @5xl:grid-cols-3">
        <div class="relative overflow-hidden rounded-2xl hero p-5 text-white sm:p-6 @2xl:col-span-2 @5xl:col-span-1">
            <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full border border-white/10"></div><div class="pointer-events-none absolute -right-6 -top-6 h-28 w-28 rounded-full border border-white/10"></div>
            <div class="relative">
                <div class="flex items-center justify-between gap-2 text-sm font-medium text-brand-100"><span>💸 Dinheiro livre hoje</span><span class="text-xs text-brand-200">até {{ $safe['period_end']->format('d/m') }}</span></div>
                @if($safe['negative'])
                    <div class="mt-3 font-display text-3xl font-bold">Segura a onda ✋</div>
                    <p class="mt-2 text-sm text-brand-100">Depois de separar contas, faturas, metas e dívidas até {{ $safe['period_end']->format('d/m') }}, faltam <b class="text-white">{{ brl(abs($safe['free'])) }}</b>. Evite gastos não essenciais.</p>
                @else
                    <div class="mt-3 whitespace-nowrap font-display text-[2.6rem] font-semibold leading-none tracking-tight tabular sm:text-5xl">{{ brl($safe['today_left']) }}</div>
                    <p class="mt-2 text-sm text-brand-100">Ritmo seguro: <b class="text-white">{{ brl($safe['per_day']) }}/dia</b> por {{ $safe['days_left'] }} dia(s). Hoje você já gastou {{ brl($safe['spent_today']) }}.</p>
                    <x-progress class="mt-4 !bg-white/20" :value="$safe['per_day'] > 0 ? $safe['spent_today'] / $safe['per_day'] * 100 : 0" color="bg-white" />
                @endif
                <div x-data="{ open: false }" class="mt-4">
                    <button type="button" @click="open = !open" class="text-xs font-semibold text-white/90 underline-offset-2 hover:underline" :aria-expanded="open">Como calculamos? <span x-text="open ? '▲' : '▼'"></span></button>
                    <dl x-show="open" x-collapse class="mt-3 space-y-1.5 rounded-xl bg-black/15 p-3 text-xs">
                        @foreach($safe['lines'] as $l)
                            <div class="flex justify-between gap-3"><dt class="text-brand-100">{{ $l['label'] }}</dt><dd class="tabular font-semibold">{{ brl($l['value'], true) }}</dd></div>
                        @endforeach
                        <div class="flex justify-between gap-3 border-t border-white/20 pt-1.5"><dt class="font-semibold">Dinheiro livre no período</dt><dd class="tabular font-bold">{{ brl($safe['free']) }}</dd></div>
                        <p class="pt-1 text-[11px] text-brand-200">Saldo do banco ({{ brl($safe['balance']) }}) não é dinheiro livre: ele já tem destino.</p>
                    </dl>
                </div>
            </div>
        </div>

        <div class="card card-pad">
            <div class="flex items-center justify-between">
                <span class="card-title">Prumo Score</span>
                <span class="badge" style="background: {{ $scoreColor }}1a; color: {{ $scoreColor }}">{{ $score['level'] }}</span>
            </div>
            <div class="mt-3 flex items-center gap-5">
                <div class="relative h-28 w-28 shrink-0">
                    <svg viewBox="0 0 36 36" class="h-full w-full -rotate-90">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" class="text-slate-100" stroke-width="3.2"/>
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="{{ $scoreColor }}" stroke-width="3.2" stroke-linecap="round" stroke-dasharray="{{ $score['total'] / 10 }} 100"/>
                    </svg>
                    <div class="absolute inset-0 grid place-items-center text-center">
                        <div><div class="font-display text-2xl font-bold tabular text-slate-900">{{ $score['total'] }}</div><div class="text-[10px] text-slate-400">de 1000</div></div>
                    </div>
                </div>
                <div class="min-w-0 flex-1 space-y-1.5">
                    @foreach($score['pillars'] as $p)
                        <div title="{{ $p['hint'] }}">
                            <div class="flex justify-between text-[11px]"><span class="truncate text-slate-600">{{ $p['label'] }}</span><span class="tabular text-slate-400">{{ $p['value'] }}/{{ $p['max'] }}</span></div>
                            <div class="h-1 rounded-full bg-slate-100"><div class="h-1 rounded-full" style="width: {{ $p['max'] ? $p['value'] / $p['max'] * 100 : 0 }}%; background: {{ $scoreColor }}"></div></div>
                        </div>
                    @endforeach
                </div>
            </div>
            <a href="{{ route('ai.index') }}" class="mt-4 inline-flex text-xs font-semibold text-brand-700 hover:underline">Como subir minha nota? Pergunte ao consultor →</a>
        </div>

        <div class="card card-pad">
            <div class="card-title">Patrimônio líquido</div>
            <div class="mt-2 font-display text-3xl font-bold tracking-tight tabular {{ $netWorth['total'] < 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ brl($netWorth['total']) }}</div>
            @if($netWorthSeries->count() >= 2)
                @php $nwDelta = $netWorthSeries->last()->total - $netWorthSeries->first()->total; @endphp
                <div class="mt-1 text-xs {{ $nwDelta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $nwDelta >= 0 ? '▲' : '▼' }} {{ brl(abs($nwDelta)) }} desde {{ month_name($netWorthSeries->first()->date, true) }}/{{ $netWorthSeries->first()->date->format('y') }}</div>
                <div class="mt-2 h-10"><x-chart :config="[
                    'type' => 'line', 'money' => true,
                    'data' => ['labels' => $netWorthSeries->map(fn ($x) => month_name($x->date, true))->all(), 'datasets' => [['label' => 'Patrimônio', 'data' => $netWorthSeries->pluck('total')->all(), 'borderColor' => '#f53003', 'borderWidth' => 2, 'pointRadius' => 0, 'tension' => 0.35, 'fill' => false]]],
                    'options' => ['plugins' => ['legend' => ['display' => false]], 'scales' => ['x' => ['display' => false], 'y' => ['display' => false]]],
                ]" aria-label="Evolução do patrimônio" /></div>
            @endif
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">💰 Contas</dt><dd class="tabular font-medium">{{ brl($netWorth['cash']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">📈 Investimentos</dt><dd class="tabular font-medium">{{ brl($netWorth['investments']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">🎯 Metas</dt><dd class="tabular font-medium">{{ brl($netWorth['goals']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">💳 Faturas em aberto</dt><dd class="tabular font-medium text-rose-600">-{{ brl($netWorth['cards']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">⛓️ Dívidas</dt><dd class="tabular font-medium text-rose-600">-{{ brl($netWorth['debts']) }}</dd></div>
            </dl>
        </div>
    </div>

    {{-- Linha 2: mês --}}
    <div class="mt-4 stat-grid @3xl:grid-cols-4">
        <x-stat label="Receitas do mês" :value="brl($summary['income'])" tone="green" icon="⬆️" :hint="'Salário '.brl($summary['salary']).' · Extra '.brl($summary['extra'])" />
        <x-stat label="Despesas do mês" :value="brl($summary['expense'])" tone="red" icon="⬇️" :hint="'Fixos '.brl($summary['fixed']).' · Parcelas '.brl($summary['installments']).' · Dia a dia '.brl($summary['daily'])" />
        <x-stat label="Resultado previsto" :value="brl($summary['balance'])" :tone="$summary['balance'] >= 0 ? 'brand' : 'red'" icon="⚖️" :hint="$summary['income'] > 0 ? 'Você guarda '.pct($summary['savings_rate']).' da renda' : 'Cadastre sua renda'" />
        <x-stat label="Saldo em contas hoje" :value="brl($netWorth['cash'])" icon="🏦" :hint="$accounts->count().' conta(s) · '.$cards->count().' cartão(ões)'" />
    </div>

    {{-- Radar de problemas --}}
    @if($radar->count())
        <div class="mt-6 rounded-2xl border border-rose-200 bg-white p-4 sm:p-5">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-display text-base font-semibold text-slate-900">📡 Radar de problemas</h3>
                <a href="{{ route('radar.index') }}" class="text-xs font-semibold text-brand-700 hover:underline">Ver todos →</a>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach($radar as $a)
                    <a href="{{ $a['url'] }}" class="flex gap-3 rounded-xl p-3 transition hover:bg-slate-50 {{ $a['severity'] === 'critical' ? 'bg-rose-50/70' : 'bg-amber-50/60' }}">
                        <span class="text-2xl">{{ $a['icon'] }}</span>
                        <span class="min-w-0"><span class="block text-sm font-semibold text-slate-900">{{ $a['title'] }}</span><span class="mt-0.5 block text-xs text-slate-600">{{ $a['text'] }}</span></span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Insights --}}
    @if($insights->count())
        <div class="mt-6">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-display text-base font-semibold text-slate-900">💡 O Prumo percebeu</h3>
                <a href="{{ route('ai.index') }}" class="text-xs font-semibold text-brand-700 hover:underline">Conversar com o consultor →</a>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 @5xl:grid-cols-4">
                @foreach($insights as $i)
                    <a href="{{ $i['link'] }}" class="flex gap-3 rounded-2xl border p-4 transition hover:shadow-sm {{ $levelStyles[$i['level']] ?? 'border-slate-200 bg-white' }}">
                        <span class="text-2xl">{{ $i['icon'] }}</span>
                        <span class="min-w-0"><span class="block text-sm font-semibold text-slate-900">{{ $i['title'] }}</span><span class="mt-0.5 block text-xs text-slate-600">{{ $i['text'] }}</span></span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Gráficos --}}
    <div class="mt-6 grid gap-4 @3xl:grid-cols-3">
        <div class="card card-pad @3xl:col-span-2">
            <div class="flex items-center justify-between">
                <div class="card-title">Projeção de saldo — próximos 6 meses</div>
                <a href="{{ route('reports.index') }}" class="text-xs font-semibold text-brand-700 hover:underline">12 meses →</a>
            </div>
            <p class="muted mt-1 text-xs">Considera salários, gastos fixos, parcelas, faturas e sua média de gastos do dia a dia.</p>
            <div class="mt-4 h-64">
                <x-chart :config="[
                    'type' => 'bar',
                    'data' => [
                        'labels' => array_column($projection, 'label'),
                        'datasets' => [
                            ['type' => 'line', 'label' => 'Saldo projetado', 'data' => array_column($projection, 'balance'), 'borderColor' => '#f53003', 'backgroundColor' => '#f5300318', 'fill' => true, 'tension' => 0.35, 'pointRadius' => 3],
                            ['label' => 'Entradas', 'data' => array_column($projection, 'income'), 'backgroundColor' => '#10b98188', 'borderRadius' => 6],
                            ['label' => 'Saídas', 'data' => array_column($projection, 'expense'), 'backgroundColor' => '#a1a09a66', 'borderRadius' => 6],
                        ],
                    ],
                ]" />
            </div>
        </div>
        <div class="card card-pad">
            <div class="card-title">Para onde foi o dinheiro</div>
            @if($byCategory->count())
                <div class="relative mx-auto mt-4 h-44 w-44">
                    <x-chart :config="[
                        'type' => 'doughnut',
                        'data' => ['labels' => $byCategory->pluck('name'), 'datasets' => [['data' => $byCategory->pluck('total'), 'backgroundColor' => $byCategory->pluck('color'), 'borderWidth' => 2, 'borderColor' => '#fff']]],
                        'options' => ['cutout' => '72%', 'plugins' => ['legend' => ['display' => false]]],
                    ]" />
                    <div class="pointer-events-none absolute inset-0 grid place-items-center text-center"><div><div class="text-[11px] text-slate-400">Total</div><div class="font-display text-lg font-bold tabular">{{ brl($byCategory->sum('total')) }}</div></div></div>
                </div>
                <ul class="mt-4 space-y-2">
                    @foreach($byCategory->take(5) as $c)
                        <li class="flex items-center gap-2 text-sm">
                            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $c['color'] }}"></span>
                            <span class="flex-1 truncate">{{ $c['icon'] }} {{ $c['name'] }}</span>
                            <span class="tabular font-medium">{{ brl($c['total']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty icon="🍃" title="Nenhum gasto este mês" text="Seus gastos por categoria aparecem aqui." />
            @endif
        </div>
    </div>

    <div class="mt-4 grid gap-4 @2xl:grid-cols-2 @5xl:grid-cols-3">
        {{-- Próximos vencimentos --}}
        <div class="card card-pad">
            <div class="flex items-center justify-between">
                <div class="card-title">⏰ Próximos vencimentos</div>
                <a href="{{ route('calendar.index') }}" class="text-xs font-semibold text-brand-700 hover:underline">Calendário →</a>
            </div>
            <ul class="mt-3 divide-y divide-slate-100">
                @forelse($upcoming as $u)
                    <li class="flex items-center gap-3 py-2.5">
                        <div class="w-11 shrink-0 rounded-lg py-1 text-center {{ $u['overdue'] ? 'bg-rose-50 text-rose-700' : 'bg-slate-50 text-slate-700' }}">
                            <div class="text-sm font-bold leading-none">{{ $u['date']->format('d') }}</div>
                            <div class="text-[10px] uppercase">{{ month_name($u['date'], true) }}</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium">{{ $u['icon'] }} {{ $u['label'] }}</div>
                            @if($u['overdue'])<span class="badge-red">vencida</span>@endif
                        </div>
                        <div class="text-right">
                            <div class="tabular text-sm font-semibold {{ $u['type'] === 'income' ? 'money-pos' : 'text-slate-800' }}">{{ brl($u['amount']) }}</div>
                            @if($u['id'])
                                <form method="POST" action="{{ route('transactions.toggle', $u['id']) }}">@csrf @method('PATCH')
                                    <button class="text-[11px] font-semibold text-brand-700 hover:underline">{{ $u['type'] === 'income' ? 'recebi' : 'paguei' }}</button>
                                </form>
                            @elseif(isset($u['card']))
                                <a href="{{ route('cards.invoice', ['card' => $u['card'], 'mes' => substr($u['invoice'], 0, 7)]) }}" class="text-[11px] font-semibold text-brand-700 hover:underline">ver fatura</a>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-slate-500">Nada vencendo nos próximos dias. 😌</li>
                @endforelse
            </ul>
        </div>

        {{-- Orçamentos --}}
        <div class="card card-pad">
            <div class="flex items-center justify-between">
                <div class="card-title">📊 Orçamentos do mês</div>
                <a href="{{ route('budgets.index') }}" class="text-xs font-semibold text-brand-700 hover:underline">Ajustar →</a>
            </div>
            <div class="mt-4 space-y-4">
                @forelse($budgets as $b)
                    <div>
                        <div class="mb-1 flex justify-between text-sm"><span>{{ $b['category']->icon }} {{ $b['category']->name }}</span><span class="tabular text-slate-500">{{ brl($b['spent']) }} / {{ brl($b['budget']) }}</span></div>
                        <x-progress :value="$b['pct']" />
                    </div>
                @empty
                    <x-empty icon="🎚️" title="Sem orçamentos" text="Defina limites por categoria e o Prumo avisa quando estiver acelerando.">
                        <a href="{{ route('budgets.index') }}" class="btn-secondary btn-sm">Criar orçamentos</a>
                    </x-empty>
                @endforelse
            </div>
        </div>

        {{-- Casal ou radar --}}
        @if($couple)
            <div class="card card-pad">
                <div class="flex items-center justify-between">
                    <div class="card-title">💑 Acerto do casal</div>
                    <a href="{{ route('couple.index') }}" class="text-xs font-semibold text-brand-700 hover:underline">Detalhes →</a>
                </div>
                <p class="muted mt-1 text-xs">Despesas compartilhadas do mês: {{ brl($couple['total_shared']) }}</p>
                <div class="mt-4 space-y-3">
                    @foreach($couple['members'] as $m)
                        <div class="flex items-center gap-3">
                            <div class="grid h-9 w-9 place-items-center rounded-full bg-brand-100 text-xs font-bold text-brand-800">{{ $m['user']->initials() }}</div>
                            <div class="flex-1 text-sm"><div class="font-medium">{{ $m['user']->firstName() }} <span class="text-xs text-slate-400">({{ pct($m['share_pct'], 0) }})</span></div><div class="text-xs text-slate-500">pagou {{ brl($m['paid']) }} · justo {{ brl($m['fair']) }}</div></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 rounded-xl bg-brand-50 p-3 text-center text-sm text-brand-900">
                    @if($couple['transfer'])
                        <b>{{ $couple['transfer']['from']->firstName() }}</b> → <b>{{ $couple['transfer']['to']->firstName() }}</b>: <b class="tabular">{{ brl($couple['transfer']['amount']) }}</b>
                    @else
                        Tudo equilibrado ✨
                    @endif
                </div>
            </div>
        @else
            <div class="card card-pad">
                <div class="card-title">📡 Radar de parcelas</div>
                <p class="muted mt-1 text-xs">Quando cada parcelamento acaba e libera sua renda.</p>
                <ul class="mt-3 space-y-3">
                    @forelse($installmentRadar as $r)
                        <li>
                            <div class="flex justify-between text-sm"><span class="truncate">{{ $r['description'] }}</span><span class="tabular font-medium">{{ brl($r['amount']) }}/mês</span></div>
                            <div class="mt-1 flex items-center gap-2"><x-progress class="flex-1" :value="(1 - $r['remaining'] / max(1, $r['total'])) * 100" color="bg-violet-500" /><span class="text-[11px] text-slate-500">acaba {{ month_name($r['last_date'], true) }}/{{ $r['last_date']->format('y') }}</span></div>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-slate-500">Sem parcelamentos ativos. 🙌</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </div>

    <div class="mt-4 grid gap-4 @3xl:grid-cols-3">
        {{-- Últimos lançamentos --}}
        <div class="card @3xl:col-span-2">
            <div class="flex items-center justify-between px-6 pt-5">
                <div class="card-title">Últimos lançamentos</div>
                <a href="{{ route('transactions.index') }}" class="text-xs font-semibold text-brand-700 hover:underline">Ver todos →</a>
            </div>
            <ul class="mt-2 divide-y divide-slate-100">
                @forelse($recent as $t)
                    <li class="flex items-center gap-3 px-6 py-3">
                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-lg" style="background: {{ ($t->category?->color ?? '#94a3b8') }}1a">{{ $t->category?->icon ?? ($t->type === 'transfer' ? '🔁' : '•') }}</div>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('transactions.edit', $t) }}" class="block truncate text-sm font-medium text-slate-800 hover:underline">{{ $t->label() }}</a>
                            <div class="text-xs text-slate-500">{{ $t->date->format('d/m') }} · {{ $t->creditCard?->name ?? $t->account?->name ?? '—' }}@if($household->isCouple()) · {{ $t->user?->firstName() }}@endif</div>
                        </div>
                        <div class="text-right">
                            <div class="tabular text-sm font-semibold {{ $t->type === 'income' ? 'money-pos' : ($t->type === 'transfer' ? 'text-slate-600' : 'text-slate-800') }}">{{ $t->type === 'income' ? '+' : ($t->type === 'transfer' ? '' : '-') }}{{ brl($t->amount) }}</div>
                            @if($t->type === 'expense' && $hourly > 0)<div class="text-[11px] text-slate-400">⏱️ {{ num($t->amount / $hourly, 1) }}h de trabalho</div>@endif
                        </div>
                    </li>
                @empty
                    <li><x-empty icon="✍️" title="Nenhum lançamento ainda" text="Lance seu primeiro gasto ou importe o extrato do banco.">
                        <div class="flex gap-2"><a href="{{ route('transactions.create') }}" class="btn-primary btn-sm">Lançar gasto</a><a href="{{ route('imports.index') }}" class="btn-secondary btn-sm">Importar OFX</a></div>
                    </x-empty></li>
                @endforelse
            </ul>
        </div>

        {{-- Metas + dívidas --}}
        <div class="space-y-4">
            <div class="card card-pad">
                <div class="flex items-center justify-between">
                    <div class="card-title">🎯 Metas</div>
                    <a href="{{ route('goals.index') }}" class="text-xs font-semibold text-brand-700 hover:underline">Todas →</a>
                </div>
                <div class="mt-3 space-y-4">
                    @forelse($goals as $g)
                        <div>
                            <div class="flex justify-between text-sm"><span>{{ $g->icon }} {{ $g->name }}</span><span class="tabular text-slate-500">{{ pct($g->progress(), 0) }}</span></div>
                            <x-progress class="mt-1" :value="$g->progress()" color="bg-brand-500" />
                            <div class="mt-1 text-[11px] text-slate-500">{{ brl($g->current_amount) }} de {{ brl($g->target_amount) }}@if($g->monthlyNeeded()) · {{ brl($g->monthlyNeeded()) }}/mês @endif</div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Crie sua primeira meta — comece pela <a href="{{ route('goals.create', ['tipo' => 'emergency']) }}" class="font-semibold text-brand-700 hover:underline">reserva de emergência</a>.</p>
                    @endforelse
                </div>
            </div>
            @if($debtTotal > 0)
                <a href="{{ route('debts.index') }}" class="block rounded-2xl border border-rose-200 bg-gradient-to-br from-rose-50 to-white p-5 hover:shadow-sm">
                    <div class="text-sm font-semibold text-rose-800">⛓️ Dívidas ativas</div>
                    <div class="mt-1 font-display text-2xl font-bold tabular text-rose-700">{{ brl($debtTotal) }}</div>
                    <div class="mt-1 text-xs text-rose-700/80">Veja seu plano de saída e a data da liberdade →</div>
                </a>
            @endif
        </div>
    </div>

    <div class="mt-4 card card-pad">
        <div class="card-title">Receitas x despesas — últimos 6 meses</div>
        <div class="mt-4 h-56">
            <x-chart :config="[
                'type' => 'bar',
                'data' => ['labels' => array_column($history, 'label'), 'datasets' => [
                    ['label' => 'Receitas', 'data' => array_column($history, 'income'), 'backgroundColor' => '#10b981', 'borderRadius' => 6],
                    ['label' => 'Despesas', 'data' => array_column($history, 'expense'), 'backgroundColor' => '#fb7185', 'borderRadius' => 6],
                ]],
            ]" />
        </div>
    </div>
</x-layouts.app>
