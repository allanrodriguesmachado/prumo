@php
    /** Seta de variação: $goodUp=true quando subir é bom (receita), false quando subir é ruim (despesa). */
    $delta = function (float $cur, float $prev, bool $goodUp = true, bool $points = false) {
        if (! $points && abs($prev) < 0.01) {
            return '<span class="text-slate-400">sem base no mês anterior</span>';
        }
        $diff = $points ? $cur - $prev : ($cur - $prev) / abs($prev) * 100;
        if (abs($diff) < 0.05) {
            return '<span class="text-slate-500">= igual ao mês anterior</span>';
        }
        $up = $diff > 0;
        $good = $up === $goodUp;
        $cls = $good ? 'text-emerald-600' : 'text-rose-600';
        $txt = ($up ? '▲ ' : '▼ ').($points ? num(abs($diff), 1).' p.p.' : pct(abs($diff)));

        return '<span class="font-semibold '.$cls.'">'.e($txt).'</span> <span class="text-slate-400">vs mês anterior</span>';
    };
    $weekdays = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    $maxWeekday = $weekday->max() > 0 ? $weekday->search($weekday->max()) : null;
    $catTotal = $byCategory->sum('total');
    $ruleLabels = ['needs' => ['Necessidades', '🏠', 'bg-sky-500'], 'wants' => ['Desejos', '🎉', 'bg-violet-500'], 'savings' => ['Futuro (poupança, dívidas, metas)', '🌱', 'bg-emerald-500']];
@endphp

<x-layouts.app title="Relatórios">
    <x-page-header title="Relatórios" subtitle="Entenda para onde vai o seu dinheiro — e o que vem pela frente.">
        <x-slot:actions>
            <x-month-nav :month="$month" route="reports.index" />
            <a href="{{ route('transactions.export', ['mes' => $month->format('Y-m')]) }}" class="btn-secondary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>
                Exportar CSV
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Resumo --}}
    <div class="stat-grid @3xl:grid-cols-4">
        <x-stat label="Receitas" :value="brl($summary['income'])" tone="green" icon="💰" :hint="$delta($summary['income'], $prevSummary['income'], true)" />
        <x-stat label="Despesas" :value="brl($summary['expense'])" tone="red" icon="🧾" :hint="$delta($summary['expense'], $prevSummary['expense'], false)" />
        <x-stat label="Saldo do mês" :value="brl($summary['balance'])" :tone="$summary['balance'] >= 0 ? 'brand' : 'red'" icon="⚖️" :hint="$delta($summary['balance'], $prevSummary['balance'], true)" />
        <x-stat label="Taxa de poupança" :value="pct($summary['savings_rate'])" :tone="$summary['savings_rate'] >= 20 ? 'green' : ($summary['savings_rate'] >= 0 ? 'amber' : 'red')" icon="🌱" :hint="$delta($summary['savings_rate'], $prevSummary['savings_rate'], true, true)" />
    </div>

    {{-- Histórico 12 meses --}}
    <div class="card card-pad mt-6">
        <div class="flex items-center justify-between">
            <h3 class="card-title">Receitas x despesas — últimos 12 meses</h3>
        </div>
        <div class="mt-4 h-72"><x-chart :config="[
            'type' => 'bar',
            'data' => [
                'labels' => array_column($history, 'label'),
                'datasets' => [
                    ['label' => 'Receitas', 'data' => array_column($history, 'income'), 'backgroundColor' => '#10b981', 'borderRadius' => 6],
                    ['label' => 'Despesas', 'data' => array_column($history, 'expense'), 'backgroundColor' => '#f43f5e', 'borderRadius' => 6],
                ],
            ],
        ]" /></div>
    </div>

    {{-- Categorias --}}
    <div class="mt-6 grid gap-6 @4xl:grid-cols-5">
        <div class="card card-pad @4xl:col-span-2">
            <h3 class="card-title">Gastos por categoria</h3>
            @if($byCategory->isEmpty())
                <x-empty icon="🧾" title="Nenhum gasto neste mês" text="Quando você lançar despesas, o gráfico aparece aqui." />
            @else
                <div class="relative mt-4 h-60">
                    <x-chart :config="[
                        'type' => 'doughnut',
                        'data' => [
                            'labels' => $byCategory->pluck('name')->all(),
                            'datasets' => [['data' => $byCategory->pluck('total')->all(), 'backgroundColor' => $byCategory->pluck('color')->all(), 'borderWidth' => 0]],
                        ],
                        'options' => ['cutout' => '70%', 'plugins' => ['legend' => ['display' => false]]],
                    ]" />
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-xs text-slate-500">Total</span>
                        <span class="font-display text-xl font-bold tabular text-slate-900">{{ brl($catTotal) }}</span>
                    </div>
                </div>
            @endif
        </div>
        <div class="card @4xl:col-span-3">
            <div class="card-pad pb-2"><h3 class="card-title">Comparação com {{ month_name($month->copy()->subMonthNoOverflow()) }}</h3></div>
            @if($byCategory->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Categoria</th><th class="text-right">Este mês</th><th class="text-right">Anterior</th><th class="text-right">Variação</th></tr></thead>
                        <tbody>
                        @foreach($byCategory as $c)
                            @php $var = $c['prev'] > 0 ? ($c['total'] - $c['prev']) / $c['prev'] * 100 : null; @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $c['color'] }}"></span>
                                        <span>{{ $c['icon'] }} {{ $c['name'] }}</span>
                                    </div>
                                    @if($c['budget'])
                                        <div class="mt-1.5 flex items-center gap-2">
                                            <x-progress :value="$c['total'] / $c['budget'] * 100" class="w-24" />
                                            <span class="text-[11px] text-slate-400">{{ pct($c['total'] / $c['budget'] * 100, 0) }} de {{ brl($c['budget']) }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-right tabular font-semibold money-neg">{{ brl($c['total']) }}</td>
                                <td class="text-right tabular text-slate-500">{{ $c['prev'] > 0 ? brl($c['prev']) : '—' }}</td>
                                <td class="text-right tabular text-xs">
                                    @if($var === null)
                                        <span class="badge-slate">novo</span>
                                    @elseif(abs($var) < 0.5)
                                        <span class="text-slate-400">=</span>
                                    @else
                                        <span class="{{ $var > 0 ? 'text-rose-600' : 'text-emerald-600' }} font-semibold">{{ $var > 0 ? '▲' : '▼' }} {{ pct(abs($var), 0) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="card-pad pt-0 text-sm text-slate-500">Sem despesas para comparar.</p>
            @endif
        </div>
    </div>

    {{-- Diário + dia da semana --}}
    <div class="mt-6 grid gap-6 @4xl:grid-cols-3">
        <div class="card card-pad @4xl:col-span-2">
            <h3 class="card-title">Gastos dia a dia</h3>
            <p class="muted text-xs">Total gasto em cada dia de {{ month_name($month) }}.</p>
            <div class="mt-4 h-56"><x-chart :config="[
                'type' => 'bar',
                'data' => [
                    'labels' => range(1, $daily->count()),
                    'datasets' => [['label' => 'Gastos', 'data' => $daily->values()->all(), 'backgroundColor' => '#f59e0b', 'borderRadius' => 4]],
                ],
            ]" /></div>
        </div>
        <div class="card card-pad">
            <h3 class="card-title">Por dia da semana</h3>
            <p class="muted text-xs">Gastos do dia a dia nos últimos 3 meses.</p>
            <div class="mt-4 h-44"><x-chart :config="[
                'type' => 'bar',
                'data' => [
                    'labels' => ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
                    'datasets' => [['label' => 'Gastos', 'data' => $weekday->values()->all(), 'backgroundColor' => '#8b5cf6', 'borderRadius' => 4]],
                ],
            ]" /></div>
            @if($maxWeekday !== null)
                <div class="mt-3 rounded-xl bg-violet-50 px-3 py-2 text-sm text-violet-800">
                    💡 Você gasta mais {{ in_array($maxWeekday, [0, 6]) ? 'aos' : 'às' }} <strong>{{ mb_strtolower($weekdays[$maxWeekday]) }}{{ in_array($maxWeekday, [0, 6]) ? 's' : 's-feiras' }}</strong>. Que tal planejar esse dia com antecedência?
                </div>
            @endif
        </div>
    </div>

    {{-- 50/30/20 + receitas --}}
    <div class="mt-6 grid gap-6 @3xl:grid-cols-2">
        <div class="card card-pad">
            <h3 class="card-title">Regra 50/30/20</h3>
            <p class="muted text-xs">Uma referência simples: 50% para o essencial, 30% para desejos e 20% para o futuro.</p>
            <div class="mt-4 space-y-4">
                @foreach($ruleLabels as $key => [$label, $icon, $color])
                    @php $r = $rule[$key]; $ok = $key === 'savings' ? $r['pct'] >= $r['ideal'] : $r['pct'] <= $r['ideal']; @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-slate-700">{{ $icon }} {{ $label }}</span>
                            <span class="tabular">
                                <strong class="{{ $ok ? 'text-emerald-600' : 'text-amber-600' }}">{{ pct($r['pct'], 0) }}</strong>
                                <span class="text-slate-400">/ ideal {{ $r['ideal'] }}%</span>
                            </span>
                        </div>
                        <div class="relative mt-1.5">
                            <x-progress :value="$r['pct']" :color="$color" />
                            <span class="absolute top-[-3px] h-[calc(100%+6px)] w-0.5 rounded bg-slate-700/60" style="left: {{ $r['ideal'] }}%" title="Ideal"></span>
                        </div>
                        <div class="mt-1 text-xs text-slate-500 tabular">{{ brl($r['total']) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card card-pad">
            <h3 class="card-title">De onde veio a renda</h3>
            @forelse($incomeByCategory as $c)
                @php $p = $summary['income'] > 0 ? $c['total'] / $summary['income'] * 100 : 0; @endphp
                <div class="mt-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-700">{{ $c['icon'] }} {{ $c['name'] }}</span>
                        <span class="tabular money-pos font-semibold">{{ brl($c['total']) }} <span class="text-xs font-normal text-slate-400">{{ pct($p, 0) }}</span></span>
                    </div>
                    <x-progress :value="$p" color="bg-emerald-500" class="mt-1.5" />
                </div>
            @empty
                <x-empty icon="💼" title="Nenhuma receita neste mês" text="Cadastre seu salário em Renda & fixos para acompanhar." />
            @endforelse
        </div>
    </div>

    {{-- Projeção --}}
    <div class="card card-pad mt-6">
        <h3 class="card-title">Projeção de caixa — próximos 12 meses</h3>
        <p class="muted text-xs">Inclui seus fixos e salários (recorrências), parcelas futuras, faturas de cartão, parcelas mínimas de dívidas e a média dos gastos do dia a dia.</p>
        <div class="mt-4 h-72"><x-chart :config="[
            'type' => 'line',
            'data' => [
                'labels' => array_column($projection, 'label'),
                'datasets' => [
                    ['label' => 'Saldo projetado', 'data' => array_column($projection, 'balance'), 'borderColor' => '#1a7d66', 'backgroundColor' => 'rgba(40,155,126,0.12)', 'fill' => true, 'tension' => 0.35, 'borderWidth' => 2.5, 'pointRadius' => 3],
                    ['label' => 'Entradas', 'data' => array_column($projection, 'income'), 'borderColor' => '#10b981', 'borderDash' => [4, 4], 'tension' => 0.35, 'pointRadius' => 0, 'borderWidth' => 1.5],
                    ['label' => 'Saídas', 'data' => array_column($projection, 'expense'), 'borderColor' => '#f43f5e', 'borderDash' => [4, 4], 'tension' => 0.35, 'pointRadius' => 0, 'borderWidth' => 1.5],
                ],
            ],
        ]" /></div>
        @php $firstNeg = collect($projection)->first(fn ($p) => $p['balance'] < 0); @endphp
        @if($firstNeg)
            <div class="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-800">
                ⚠️ No ritmo atual, o saldo fica negativo em <strong>{{ $firstNeg['label'] }}</strong>. Ainda dá tempo de ajustar — pequenos cortes agora fazem muita diferença lá na frente.
            </div>
        @endif
        <details class="mt-3">
            <summary class="cursor-pointer text-xs font-medium text-brand-700">Ver tabela mês a mês</summary>
            <div class="mt-2 overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Mês</th><th class="text-right">Entradas</th><th class="text-right">Saídas</th><th class="text-right">Parcelas</th><th class="text-right">Faturas</th><th class="text-right">Saldo</th></tr></thead>
                    <tbody>
                    @foreach($projection as $p)
                        <tr>
                            <td>{{ $p['label'] }}</td>
                            <td class="text-right tabular money-pos">{{ brl($p['income']) }}</td>
                            <td class="text-right tabular money-neg">{{ brl($p['expense']) }}</td>
                            <td class="text-right tabular text-slate-500">{{ brl($p['installments']) }}</td>
                            <td class="text-right tabular text-slate-500">{{ brl($p['cards']) }}</td>
                            <td class="text-right tabular font-semibold {{ $p['balance'] < 0 ? 'money-neg' : 'text-slate-900' }}">{{ brl($p['balance']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </div>

    {{-- Radar de parcelas + assinaturas --}}
    <div class="mt-6 grid gap-6 @3xl:grid-cols-2">
        <div class="card card-pad">
            <h3 class="card-title">📡 Radar de parcelas</h3>
            <p class="muted text-xs">Quando cada parcelamento termina e quanto da sua renda fica livre.</p>
            @forelse($radar as $r)
                @php $done = $r['total'] > 0 ? ($r['total'] - $r['remaining']) / $r['total'] * 100 : 0; @endphp
                <div class="mt-4 border-t border-slate-100 pt-3 first-of-type:border-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-slate-800">{{ $r['description'] }}</div>
                            <div class="text-xs text-slate-500">
                                Faltam {{ $r['remaining'] }} de {{ $r['total'] }} · termina em <span class="capitalize">{{ month_name($r['last_date']) }}</span>/{{ $r['last_date']->year }}
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="text-sm font-semibold tabular money-neg">{{ brl($r['amount']) }}/mês</div>
                            <div class="text-[11px] text-slate-400 tabular">{{ brl($r['remaining_value']) }} restantes</div>
                        </div>
                    </div>
                    <x-progress :value="$done" color="bg-brand-500" class="mt-2" />
                    <div class="mt-1 text-[11px] text-emerald-700">
                        🎉 A partir de <span class="capitalize">{{ month_name($r['last_date']->copy()->addMonthNoOverflow()) }}</span> sobram +{{ brl($r['amount']) }} por mês.
                    </div>
                </div>
            @empty
                <x-empty icon="🧘" title="Nenhum parcelamento em aberto" text="Sem parcelas comprometendo sua renda. Ótimo!" />
            @endforelse
        </div>

        <div class="card card-pad">
            <h3 class="card-title">🔁 Assinaturas detectadas</h3>
            <p class="muted text-xs">Gastos que se repetem todo mês e não estão cadastrados como fixos. Vale revisar se ainda fazem sentido.</p>
            @forelse($subscriptions as $s)
                <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 first-of-type:border-0">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-slate-800">{{ $s['description'] }}</div>
                        <div class="text-xs text-slate-500">Visto em {{ $s['months'] }} lançamentos recentes</div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="text-sm font-semibold tabular money-neg">{{ brl($s['amount']) }}</div>
                        <div class="text-[11px] text-slate-400 tabular">{{ brl($s['yearly']) }}/ano</div>
                    </div>
                </div>
            @empty
                <x-empty icon="🔍" title="Nada suspeito por aqui" text="Não encontramos cobranças repetidas esquecidas." />
            @endforelse
            @if($subscriptions->isNotEmpty())
                <div class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Juntas, somam <strong class="tabular">{{ brl($subscriptions->sum('yearly')) }}</strong> por ano.
                </div>
            @endif
        </div>
    </div>

    {{-- Top gastos + patrimônio --}}
    <div class="mt-6 grid gap-6 @4xl:grid-cols-5">
        <div class="card @4xl:col-span-3">
            <div class="card-pad pb-2">
                <h3 class="card-title">Top 10 gastos do mês</h3>
                @if($hourly > 0)<p class="muted text-xs">Seu trabalho vale cerca de {{ brl($hourly) }} por hora.</p>@endif
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse($top as $i => $t)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span class="w-5 text-xs font-bold text-slate-400">{{ $i + 1 }}</span>
                        <span class="text-lg">{{ $t->category?->icon ?? '•' }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-slate-800">{{ $t->label() }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $t->date->format('d/m') }} · {{ $t->category?->name ?? 'Sem categoria' }}
                                @if($hourly > 0) · <span class="text-amber-700">custou {{ num($t->amount / $hourly, 1) }} horas de trabalho</span>@endif
                            </div>
                        </div>
                        <span class="text-sm font-semibold tabular money-neg">{{ brl($t->amount) }}</span>
                    </li>
                @empty
                    <li><x-empty icon="🧾" title="Nenhum gasto neste mês" /></li>
                @endforelse
            </ul>
        </div>

        <div class="card card-pad @4xl:col-span-2">
            <h3 class="card-title">Patrimônio líquido</h3>
            <div class="mt-2 font-display text-3xl font-bold tabular {{ $netWorth['total'] >= 0 ? 'text-slate-900' : 'text-rose-600' }}">{{ brl($netWorth['total']) }}</div>
            <p class="muted text-xs">O que você tem menos o que você deve, hoje.</p>
            <dl class="mt-4 space-y-2.5 text-sm">
                @foreach([['cash', '🏦 Contas', true], ['investments', '📈 Investimentos', true], ['goals', '🎯 Guardado em metas', true], ['debts', '⛓️ Dívidas', false], ['cards', '💳 Faturas em aberto', false]] as [$k, $l, $pos])
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-600">{{ $l }}</dt>
                        <dd class="tabular font-semibold {{ $pos ? 'money-pos' : 'money-neg' }}">{{ $pos ? '' : '− ' }}{{ brl($netWorth[$k]) }}</dd>
                    </div>
                @endforeach
                <div class="flex items-center justify-between border-t border-slate-200 pt-2.5">
                    <dt class="font-semibold text-slate-800">Total</dt>
                    <dd class="tabular font-bold {{ $netWorth['total'] >= 0 ? 'text-slate-900' : 'money-neg' }}">{{ brl($netWorth['total']) }}</dd>
                </div>
            </dl>
            @if($netWorth['total'] < 0)
                <p class="mt-4 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">Patrimônio negativo é um ponto de partida, não um destino. Cada dívida quitada move esse número para cima. 💪</p>
            @endif
        </div>
    </div>

    <div class="card card-pad mt-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="card-title">📈 Evolução patrimonial</h3>
            @if($netWorthSeries->contains('estimated', true))<span class="badge-slate" title="Meses anteriores ao início das fotos diárias: reconstruídos só com contas e faturas">meses antigos estimados</span>@endif
        </div>
        @if($netWorthSeries->count() >= 2)
            <div class="mt-4 h-64">
                <x-chart :config="[
                    'type' => 'bar',
                    'data' => ['labels' => $netWorthSeries->map(fn ($x) => ucfirst(month_name($x->date, true)).'/'.$x->date->format('y'))->all(), 'datasets' => [
                        ['type' => 'line', 'label' => 'Patrimônio líquido', 'data' => $netWorthSeries->pluck('total')->all(), 'borderColor' => '#f53003', 'backgroundColor' => '#f5300318', 'tension' => 0.3, 'fill' => true, 'pointRadius' => 3],
                        ['label' => 'Contas', 'data' => $netWorthSeries->pluck('cash')->all(), 'backgroundColor' => '#4bb69866', 'borderRadius' => 4, 'stack' => 'a'],
                        ['label' => 'Investimentos + metas', 'data' => $netWorthSeries->map(fn ($x) => $x->investments + $x->goals)->all(), 'backgroundColor' => '#7c3aed55', 'borderRadius' => 4, 'stack' => 'a'],
                        ['label' => 'Dívidas + faturas', 'data' => $netWorthSeries->map(fn ($x) => -($x->debts + $x->cards))->all(), 'backgroundColor' => '#f43f5e55', 'borderRadius' => 4, 'stack' => 'a'],
                    ]],
                    'options' => ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true]]],
                ]" aria-label="Evolução patrimonial mês a mês" />
            </div>
        @else
            <p class="muted mt-3">A partir de hoje o Prumo registra uma foto diária do seu patrimônio. Em alguns dias o gráfico de evolução aparece aqui.</p>
        @endif
    </div>
</x-layouts.app>