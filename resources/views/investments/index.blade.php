@php
    $rateLabel = function (\App\Models\Asset $a) {
        $r = $a->rate !== null ? num($a->rate, fmod((float) $a->rate, 1) == 0 ? 0 : 2) : null;
        return match ($a->indexer) {
            'cdi' => $r !== null ? "{$r}% do CDI" : 'CDI',
            'selic' => $r !== null && (float) $a->rate > 0 ? "Selic + {$r}%" : 'Selic',
            'ipca' => $r !== null ? "IPCA + {$r}%" : 'IPCA +',
            'prefixed' => $r !== null ? "{$r}% a.a." : 'Prefixado',
            'savings' => 'Regra da poupança',
            default => $a->class === 'savings' ? 'Regra da poupança' : null,
        };
    };

    $ind = is_array($indicators ?? null) ? $indicators : [];
    $fxData = is_array($fx ?? null) ? $fx : [];
    $indVal = fn ($k) => isset($ind[$k]['value']) && $ind[$k]['value'] !== null ? pct($ind[$k]['value'], 2) : '—';

    $strip = [
        ['Selic', $indVal('selic'), 'meta a.a.', null],
        ['CDI', $indVal('cdi'), 'ao ano', null],
        ['IPCA 12m', $indVal('ipca_12m'), 'inflação', null],
    ];
    foreach (['USDBRL' => ['Dólar', 2], 'EURBRL' => ['Euro', 2], 'BTCBRL' => ['Bitcoin', 0]] as $code => [$lbl, $dec]) {
        $q = $fxData[$code] ?? null;
        $strip[] = [$lbl, $q && isset($q['bid']) ? 'R$ '.num($q['bid'], $dec) : '—', null, $q['change'] ?? null];
    }

    // proventos: últimos 12 meses preenchendo os vazios
    $divLabels = []; $divValues = [];
    for ($i = 11; $i >= 0; $i--) {
        $m = now()->startOfMonth()->subMonths($i);
        $divLabels[] = ucfirst(month_name($m, true)).'/'.$m->format('y');
        $divValues[] = round((float) ($dividends[$m->format('Y-m')] ?? 0), 2);
    }
    $divTotal = array_sum($divValues);

    $gainPct = $cost > 0 ? $gain / $cost * 100 : 0;
    $gainHint = '<span class="font-semibold '.($gain >= 0 ? 'text-emerald-600' : 'text-rose-600').'">'.($gainPct >= 0 ? '+' : '').pct($gainPct, 2).'</span> sobre o custo, com proventos';
    $coverage = $monthlySpending > 0 ? $passiveIncome / $monthlySpending * 100 : 0;
    $byClassTotal = collect($byClass)->sum('value');
    $grouped = $assets->groupBy('class');
@endphp

<x-layouts.app title="Investimentos">
    <x-page-header title="Investimentos" subtitle="Sua carteira num só lugar — cada aporte é um passo rumo à tranquilidade.">
        <x-slot:actions>
            <form method="POST" action="{{ route('investments.refresh') }}">
                @csrf
                <button class="btn-secondary">🔄 Atualizar cotações</button>
            </form>
            <a href="{{ route('investments.simulators') }}" class="btn-secondary">🧮 Simuladores</a>
            <a href="{{ route('investments.create') }}" class="btn-primary">+ Novo ativo</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Indicadores de mercado --}}
    <div class="mb-6 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <div class="flex min-w-max gap-2 sm:grid sm:min-w-0 sm:grid-cols-3 @4xl:grid-cols-6">
            @foreach($strip as [$lbl, $val, $sub, $chg])
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $lbl }}</div>
                    <div class="flex items-baseline gap-1.5">
                        <span class="font-display text-base font-bold text-slate-900 tabular">{{ $val }}</span>
                        @if($chg !== null)
                            <span class="text-[11px] font-semibold tabular {{ $chg >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $chg >= 0 ? '▲' : '▼' }} {{ pct(abs($chg), 2) }}</span>
                        @elseif($sub)
                            <span class="text-[11px] text-slate-400">{{ $sub }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if($assets->isEmpty())
        <div class="card">
            <x-empty icon="🌱" title="Sua carteira ainda está vazia" text="Todo patrimônio começa com o primeiro aporte — mesmo que pequeno. Escolha o tipo de investimento para começar:">
                <div class="flex max-w-xl flex-wrap justify-center gap-2">
                    @foreach(\App\Models\Asset::CLASSES as $key => [$label, $color])
                        <a href="{{ route('investments.create', ['classe' => $key]) }}"
                           class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:border-brand-300 hover:bg-brand-50">
                            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $color }}"></span>{{ $label }}
                        </a>
                    @endforeach
                </div>
            </x-empty>
        </div>
    @else
        {{-- Resumo --}}
        <div class="stat-grid @3xl:grid-cols-4">
            <x-stat label="Patrimônio investido" :value="brl($total)" tone="brand" icon="💼" :hint="$assets->count().' ativo(s) na carteira'" />
            <x-stat label="Custo (quanto você aplicou)" :value="brl($cost)" icon="🪙" hint="Preço médio × quantidade atual" />
            <x-stat label="Resultado" :value="brl($gain, true)" :tone="$gain >= 0 ? 'green' : 'red'" :icon="$gain >= 0 ? '📈' : '📉'"
                    :hint="$gainHint" />
            <x-stat label="Proventos recebidos" :value="brl($income)" tone="violet" icon="🎁" hint="Dividendos, JCP e rendimentos" />
        </div>

        {{-- Comparação com CDI --}}
        @if($cdiFactor && $firstDate && $cost > 0)
            @php $cdiValue = $cost * $cdiFactor; $diff = $total - $cdiValue; @endphp
            <div class="card card-pad mt-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="card-title">Comparado ao CDI</div>
                        <p class="muted mt-0.5 text-sm">Se o seu custo estivesse aplicado a 100% do CDI desde {{ $firstDate->format('d/m/Y') }}.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-6 text-sm">
                        <div>
                            <div class="text-xs text-slate-500">No CDI seria</div>
                            <div class="font-display text-lg font-bold tabular text-slate-700">{{ brl($cdiValue) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500">Sua carteira vale</div>
                            <div class="font-display text-lg font-bold tabular {{ $diff >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ brl($total) }}</div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-sm">
                    @if($diff >= 0)
                        <span class="badge-green">🏆 {{ brl($diff) }} acima do CDI</span>
                    @else
                        <span class="badge-amber">{{ brl(abs($diff)) }} abaixo do CDI</span>
                        <span class="muted ml-1 text-xs">Normal em renda variável no curto prazo — o que importa é o longo prazo.</span>
                    @endif
                </div>
                <p class="help mt-2">Comparação aproximada: considera todo o custo aplicado desde o primeiro aporte e ignora proventos/vendas e o momento de cada aporte.</p>
            </div>
        @endif

        <div class="mt-4 grid gap-4 @4xl:grid-cols-3">
            {{-- Alocação --}}
            <div class="card card-pad">
                <div class="card-title">Alocação por classe</div>
                <div class="relative mx-auto mt-4 h-48 w-48">
                    <x-chart :config="[
                        'type' => 'doughnut',
                        'data' => [
                            'labels' => collect($byClass)->pluck('label')->values(),
                            'datasets' => [[
                                'data' => collect($byClass)->pluck('value')->map(fn ($v) => round($v, 2))->values(),
                                'backgroundColor' => collect($byClass)->pluck('color')->values(),
                                'borderWidth' => 0,
                            ]],
                        ],
                        'options' => ['cutout' => '70%', 'plugins' => ['legend' => ['display' => false]]],
                    ]" />
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-[11px] text-slate-400">Total</span>
                        <span class="font-display text-sm font-bold tabular">{{ brl($total) }}</span>
                    </div>
                </div>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach($byClass as $c)
                        <li class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $c['color'] }}"></span>
                            <span class="min-w-0 flex-1 truncate text-slate-600">{{ $c['label'] }}</span>
                            <span class="tabular text-slate-500">{{ brl($c['value']) }}</span>
                            <span class="w-14 text-right font-semibold tabular text-slate-800">{{ pct($byClassTotal > 0 ? $c['value'] / $byClassTotal * 100 : 0) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Proventos --}}
            <div class="card card-pad">
                <div class="flex items-baseline justify-between">
                    <div class="card-title">Proventos (12 meses)</div>
                    <span class="text-sm font-semibold tabular text-violet-600">{{ brl($divTotal) }}</span>
                </div>
                @if($divTotal > 0)
                    <div class="mt-4 h-60"><x-chart :config="[
                        'type' => 'bar',
                        'data' => ['labels' => $divLabels, 'datasets' => [['label' => 'Proventos', 'data' => $divValues, 'backgroundColor' => '#7c3aed', 'borderRadius' => 6]]],
                    ]" /></div>
                @else
                    <x-empty icon="🎁" title="Nenhum provento ainda" text="Registre dividendos, JCP e rendimentos na página de cada ativo para acompanhar sua renda." class="!py-10" />
                @endif
            </div>

            {{-- Renda passiva --}}
            <div class="card card-pad">
                <div class="card-title">Renda passiva</div>
                <p class="muted mt-0.5 text-xs">Estimativa conservadora de ~0,4% ao mês sobre o patrimônio.</p>
                <div class="mt-4 font-display text-3xl font-bold tabular text-emerald-600">{{ brl($passiveIncome) }}<span class="text-sm font-medium text-slate-400">/mês</span></div>
                @if($monthlySpending > 0)
                    <div class="mt-4">
                        <div class="mb-1.5 flex justify-between text-xs">
                            <span class="font-medium text-slate-600">Independência financeira</span>
                            <span class="font-semibold tabular text-slate-800">{{ pct(min($coverage, 999)) }}</span>
                        </div>
                        <x-progress :value="$coverage" color="bg-emerald-500" />
                        <p class="mt-3 text-sm text-slate-600">
                            Cobre <strong class="tabular">{{ pct($coverage) }}</strong> do seu custo de vida de <span class="tabular">{{ brl($monthlySpending) }}</span>/mês.
                            @if($coverage >= 100)
                                🎉 Seus investimentos já pagam suas contas!
                            @elseif($coverage >= 25)
                                Você está construindo sua liberdade. 💪
                            @else
                                Cada aporte aproxima você desse dia. 🌱
                            @endif
                        </p>
                    </div>
                @else
                    <p class="mt-4 text-sm text-slate-500">Registre seus gastos para ver quanto do seu custo de vida a carteira já cobre.</p>
                @endif
                <a href="{{ route('investments.simulators') }}" class="btn-ghost btn-sm mt-4 -ml-3">Simular independência financeira →</a>
            </div>
        </div>

        {{-- Posições --}}
        <div class="mt-6 space-y-4">
            @foreach($grouped as $class => $list)
                @php
                    [$clsLabel, $clsColor, $pricing] = \App\Models\Asset::CLASSES[$class] ?? ['Outros', '#64748b', 'manual'];
                    $clsValue = $list->sum(fn ($a) => $positions[$a->id]['value']);
                    $isMarket = in_array($pricing, ['variable', 'crypto']);
                @endphp
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between gap-3 px-5 py-4">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full" style="background: {{ $clsColor }}"></span>
                            <h3 class="card-title">{{ $clsLabel }}</h3>
                            <span class="badge-slate">{{ $list->count() }}</span>
                        </div>
                        <span class="font-semibold tabular text-slate-800">{{ brl($clsValue) }}</span>
                    </div>

                    {{-- Mobile: cartões --}}
                    <div class="divide-y divide-slate-100 border-t border-slate-100 md:hidden">
                        @foreach($list as $asset)
                            @php $p = $positions[$asset->id]; @endphp
                            <a href="{{ route('investments.show', $asset) }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-slate-800">{{ $asset->name }}</div>
                                    <div class="truncate text-xs text-slate-500">
                                        @if($isMarket)
                                            {{ $asset->ticker ?: '—' }} · {{ num($p['quantity'], $pricing === 'crypto' ? 6 : 0) }} un.
                                        @elseif($pricing === 'fixed')
                                            {{ $rateLabel($asset) }}@if($asset->maturity_date) · vence {{ $asset->maturity_date->format('d/m/Y') }}@endif
                                        @else
                                            {{ $asset->institution ?: 'Valor informado manualmente' }}
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="text-sm font-semibold tabular">{{ brl($p['value']) }}</div>
                                    <div class="text-xs font-medium tabular {{ $p['gain'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $p['gain_pct'] >= 0 ? '+' : '' }}{{ pct($p['gain_pct'], 2) }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    {{-- Desktop: tabela --}}
                    <div class="hidden overflow-x-auto md:block">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Ativo</th>
                                @if($isMarket)
                                    <th class="text-right">Quantidade</th>
                                    <th class="text-right">Preço médio</th>
                                    <th class="text-right">Último preço</th>
                                @elseif($pricing === 'fixed')
                                    <th>Rentabilidade</th>
                                    <th>Vencimento</th>
                                    <th class="text-right">Aplicado</th>
                                @else
                                    <th>Instituição</th>
                                    <th class="text-right">Aplicado</th>
                                @endif
                                <th class="text-right">Valor atual</th>
                                <th class="text-right">Resultado</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($list as $asset)
                                @php $p = $positions[$asset->id]; @endphp
                                <tr class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('investments.show', $asset) }}'">
                                    <td>
                                        <a href="{{ route('investments.show', $asset) }}" class="font-semibold text-slate-800 hover:text-brand-700">{{ $asset->name }}</a>
                                        <div class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                            @if($asset->ticker)<span class="font-mono">{{ $asset->ticker }}</span>@endif
                                            @if($asset->is_shared && household()->isCouple())<span title="Compartilhado do casal">💑</span>@endif
                                            @if($asset->price_updated_at)<span>· cotação {{ $asset->price_updated_at->diffForHumans() }}</span>@endif
                                        </div>
                                    </td>
                                    @if($isMarket)
                                        <td class="text-right tabular">{{ num($p['quantity'], $pricing === 'crypto' ? 6 : (fmod($p['quantity'], 1) == 0 ? 0 : 4)) }}</td>
                                        <td class="text-right tabular">{{ brl($p['avg_price']) }}</td>
                                        <td class="text-right tabular">
                                            @if($asset->last_price !== null)
                                                <div>{{ brl($asset->last_price) }}</div>
                                                @if($asset->day_change !== null)
                                                    <span class="{{ $asset->day_change >= 0 ? 'badge-green' : 'badge-red' }} mt-0.5">{{ $asset->day_change >= 0 ? '+' : '' }}{{ pct($asset->day_change, 2) }}</span>
                                                @endif
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                    @elseif($pricing === 'fixed')
                                        <td>{{ $rateLabel($asset) ?? '—' }}@if($asset->institution)<div class="text-xs text-slate-500">{{ $asset->institution }}</div>@endif</td>
                                        <td class="tabular">{{ $asset->maturity_date?->format('d/m/Y') ?? 'Liquidez diária' }}</td>
                                        <td class="text-right tabular">{{ brl($p['cost']) }}</td>
                                    @else
                                        <td>{{ $asset->institution ?: '—' }}</td>
                                        <td class="text-right tabular">{{ brl($p['cost']) }}</td>
                                    @endif
                                    <td class="text-right font-semibold tabular">{{ brl($p['value']) }}</td>
                                    <td class="text-right tabular">
                                        <div class="{{ $p['gain'] >= 0 ? 'money-pos' : 'money-neg' }}">{{ brl($p['gain'], true) }}</div>
                                        <div class="text-xs {{ $p['gain'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $p['gain_pct'] >= 0 ? '+' : '' }}{{ pct($p['gain_pct'], 2) }}</div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="help mt-4">Renda fixa: valor bruto estimado antes do IR, calculado com dados oficiais do Banco Central. Cotações podem ter atraso.</p>
    @endif
</x-layouts.app>
