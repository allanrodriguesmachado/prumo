@php
    $committed = $income > 0 ? $minTotal / $income * 100 : null;
    $hasDebts = $active->isNotEmpty();
    $minimumPlan = $comparison['minimum'] ?? null;

    // Gráfico: saldo devedor ao longo do tempo (plano escolhido x só o mínimo)
    $chart = null;
    if ($plan && ! empty($plan['timeline'])) {
        $planSeries = array_merge([$total], array_column($plan['timeline'], 'balance'));
        $minSeries = $minimumPlan && ! empty($minimumPlan['timeline'])
            ? array_merge([$total], array_column($minimumPlan['timeline'], 'balance'))
            : [];
        $len = min(max(count($planSeries), count($minSeries)), 121); // até 10 anos no gráfico
        $labels = [];
        for ($i = 0; $i < $len; $i++) {
            $d = now()->addMonthsNoOverflow($i);
            $labels[] = $i === 0 ? 'Hoje' : month_name($d, true).'/'.$d->format('y');
        }
        $datasets = [[
            'label' => 'Seu plano', 'data' => array_slice($planSeries, 0, $len),
            'borderColor' => '#f53003', 'backgroundColor' => 'rgba(245,48,3,0.10)', 'fill' => true,
            'tension' => 0.3, 'pointRadius' => 0, 'borderWidth' => 2.5,
        ]];
        if ($minSeries) {
            $datasets[] = [
                'label' => 'Pagando só o mínimo', 'data' => array_slice($minSeries, 0, $len),
                'borderColor' => '#f43f5e', 'backgroundColor' => 'transparent', 'borderDash' => [5, 5],
                'tension' => 0.3, 'pointRadius' => 0, 'borderWidth' => 2,
            ];
        }
        $chart = ['type' => 'line', 'data' => ['labels' => $labels, 'datasets' => $datasets],
            'options' => ['interaction' => ['mode' => 'index', 'intersect' => false], 'scales' => ['x' => ['ticks' => ['maxTicksLimit' => 8]]]]];
    }

    $compareRows = [
        'prumo' => ['🧭', \App\Services\DebtPlanner::STRATEGIES['prumo'][0]],
        'avalanche' => ['🏔️', \App\Services\DebtPlanner::STRATEGIES['avalanche'][0]],
        'snowball' => ['⛄', \App\Services\DebtPlanner::STRATEGIES['snowball'][0]],
        'minimum' => ['🐢', 'Só o mínimo'],
    ];
    $minInterest = $minimumPlan['interest'] ?? null;
@endphp

<x-layouts.app title="Sair das dívidas">
    <x-page-header title="Sair das dívidas" subtitle="Um plano claro, passo a passo, até a sua data da liberdade.">
        <x-slot:actions>
            <a href="{{ route('debts.create') }}" class="btn-primary">+ Cadastrar dívida</a>
        </x-slot:actions>
    </x-page-header>

    @if(! $hasDebts && $paid->isEmpty())
        <div class="card">
            <x-empty icon="🕊️" title="Nenhuma dívida cadastrada"
                     text="Se você tem empréstimos, cartão em atraso, cheque especial ou contas pendentes, cadastre aqui. O Prumo monta um plano realista para você sair delas — sem julgamentos.">
                <a href="{{ route('debts.create') }}" class="btn-primary">+ Cadastrar minha primeira dívida</a>
            </x-empty>
        </div>
    @else

        @if(! $hasDebts)
            {{-- Livre de dívidas --}}
            <div class="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-500 via-emerald-600 to-emerald-800 p-6 text-white shadow-lg sm:p-8">
                <div class="pointer-events-none absolute -right-6 -top-6 text-[120px] opacity-20">🎉</div>
                <div class="text-sm font-semibold uppercase tracking-wider text-emerald-100">Você conseguiu</div>
                <div class="mt-1 font-display text-3xl font-bold">Livre de dívidas! 🕊️</div>
                <p class="mt-2 max-w-xl text-sm text-emerald-50">Nenhuma dívida ativa no momento. Que tal transformar o valor que ia para as parcelas em uma reserva de emergência?</p>
                <a href="{{ route('goals.create', ['tipo' => 'emergency']) }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-brand-800 hover:bg-emerald-50">🛟 Criar reserva de emergência</a>
            </div>
        @else
            {{-- Resumo --}}
            <div class="mb-6 overflow-hidden rounded-3xl hero text-white shadow-lg">
                <div class="grid gap-px bg-white/10 sm:grid-cols-2 @4xl:grid-cols-4">
                    <div class="bg-white/[.04] p-5">
                        <div class="text-[13px] font-medium text-brand-200">Total que você deve</div>
                        <div class="mt-1 font-display text-3xl font-bold tabular">{{ brl($total) }}</div>
                        <div class="mt-1 text-xs text-brand-200/80">{{ $active->count() }} {{ $active->count() === 1 ? 'dívida ativa' : 'dívidas ativas' }}</div>
                    </div>
                    <div class="bg-white/[.04] p-5">
                        <div class="text-[13px] font-medium text-brand-200">Juros queimados por mês 🔥</div>
                        <div class="mt-1 font-display text-3xl font-bold tabular text-rose-300">{{ brl($monthlyInterest) }}</div>
                        <div class="mt-1 text-xs text-brand-200/80">≈ {{ brl($monthlyInterest / 30) }} por dia indo embora</div>
                    </div>
                    <div class="bg-white/[.04] p-5">
                        <div class="text-[13px] font-medium text-brand-200">Soma dos pagamentos mínimos</div>
                        <div class="mt-1 font-display text-3xl font-bold tabular">{{ brl($minTotal) }}</div>
                        <div class="mt-1 text-xs text-brand-200/80">por mês</div>
                    </div>
                    <div class="bg-white/[.04] p-5">
                        <div class="text-[13px] font-medium text-brand-200">Da sua renda comprometida</div>
                        @if($committed !== null)
                            <div class="mt-1 font-display text-3xl font-bold tabular {{ $committed > 35 ? 'text-rose-300' : ($committed > 25 ? 'text-amber-300' : 'text-emerald-300') }}">{{ pct($committed) }}</div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/15">
                                <div class="h-full rounded-full {{ $committed > 35 ? 'bg-rose-400' : ($committed > 25 ? 'bg-amber-400' : 'bg-emerald-400') }}" style="width: {{ min(100, $committed) }}%"></div>
                            </div>
                            <div class="mt-1 text-xs text-brand-200/80">Saudável: até 30% · renda {{ brl($income) }}</div>
                        @else
                            <div class="mt-1 font-display text-3xl font-bold">—</div>
                            <a href="{{ route('recurrences.create', ['tipo' => 'salary']) }}" class="mt-1 inline-block text-xs text-brand-200 underline">Cadastre sua renda para calcular</a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Modo Resgate --}}
            @if($rescue)
                <div class="mb-6 rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-5 sm:p-6" x-data="{ open: true }">
                    <div class="flex items-start gap-4">
                        <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-amber-100 text-2xl">🛟</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="badge-amber mb-1">Modo Resgate ativado</div>
                                    <h3 class="font-display text-lg font-bold text-slate-900">Respira. Tem saída — e você não está sozinho(a).</h3>
                                </div>
                                <button type="button" @click="open = !open" class="btn-ghost btn-sm shrink-0" x-text="open ? 'Recolher' : 'Ver passos'"></button>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">
                                Percebemos dívidas em atraso ou parcelas que pesam mais de 35% da sua renda. Isso acontece com milhões de brasileiros e não define quem você é.
                                Vamos por partes, na ordem que protege você e sua família:
                            </p>
                        </div>
                    </div>
                    <ol x-show="open" x-collapse class="mt-5 grid gap-3 @4xl:grid-cols-5">
                        <li class="rounded-2xl bg-white/80 p-4 ring-1 ring-amber-100">
                            <div class="text-xs font-bold text-amber-700">Passo 1</div>
                            <div class="mt-1 font-semibold text-slate-800">🔎 Veja tudo o que está no seu CPF</div>
                            <p class="mt-1 text-xs text-slate-600">Consulte o Registrato do Banco Central — é gratuito e mostra todas as dívidas em bancos.</p>
                            <a href="https://registrato.bcb.gov.br" target="_blank" rel="noopener" class="mt-2 inline-block text-xs font-semibold text-brand-700 hover:underline">Abrir Registrato ↗</a>
                        </li>
                        <li class="rounded-2xl bg-white/80 p-4 ring-1 ring-amber-100">
                            <div class="text-xs font-bold text-amber-700">Passo 2</div>
                            <div class="mt-1 font-semibold text-slate-800">🏠 Essenciais primeiro</div>
                            <p class="mt-1 text-xs text-slate-600">Aluguel, luz, água, gás e comida vêm antes de qualquer banco. Seus gastos fixos hoje: <strong class="tabular">{{ brl($essentials) }}</strong>/mês.</p>
                        </li>
                        <li class="rounded-2xl bg-white/80 p-4 ring-1 ring-amber-100">
                            <div class="text-xs font-bold text-amber-700">Passo 3</div>
                            <div class="mt-1 font-semibold text-slate-800">✂️ Pare o sangramento</div>
                            <p class="mt-1 text-xs text-slate-600">Evite usar cartão de crédito e cheque especial agora. São os juros mais caros do país.</p>
                        </li>
                        <li class="rounded-2xl bg-white/80 p-4 ring-1 ring-amber-100">
                            <div class="text-xs font-bold text-amber-700">Passo 4</div>
                            <div class="mt-1 font-semibold text-slate-800">🤝 Negocie com desconto</div>
                            <p class="mt-1 text-xs text-slate-600">Dívidas atrasadas costumam ter descontos grandes. Use o Serasa Limpa Nome e, quando houver, o programa Desenrola.</p>
                            <a href="https://www.serasa.com.br/limpa-nome-online/" target="_blank" rel="noopener" class="mt-2 inline-block text-xs font-semibold text-brand-700 hover:underline">Serasa Limpa Nome ↗</a>
                        </li>
                        <li class="rounded-2xl bg-white/80 p-4 ring-1 ring-amber-100">
                            <div class="text-xs font-bold text-amber-700">Passo 5</div>
                            <div class="mt-1 font-semibold text-slate-800">⚖️ Superendividamento</div>
                            <p class="mt-1 text-xs text-slate-600">A Lei 14.181/2021 garante renegociar todas as dívidas juntas preservando o mínimo para viver. Procure o Procon ou a Defensoria Pública.</p>
                        </li>
                    </ol>
                </div>
            @endif

            <div class="mb-6 grid gap-6 @4xl:grid-cols-5">
                {{-- Configuração do plano --}}
                <form method="POST" action="{{ route('debts.plan') }}" class="card card-pad @4xl:col-span-2" x-data="{ strategy: @js(old('strategy', $strategy)) }">
                    @csrf
                    <h3 class="card-title">Monte seu plano</h3>
                    <p class="muted mb-4 text-sm">Escolha a estratégia e quanto você consegue destinar às dívidas todo mês.</p>

                    <div class="space-y-2.5">
                        @foreach(\App\Services\DebtPlanner::STRATEGIES as $key => [$name, $desc])
                            <label class="flex cursor-pointer gap-3 rounded-2xl border p-3.5 transition"
                                   :class="strategy === '{{ $key }}' ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500' : 'border-slate-200 hover:border-slate-300'">
                                <input type="radio" name="strategy" value="{{ $key }}" x-model="strategy" class="mt-1 h-4 w-4 accent-brand-600">
                                <span class="min-w-0">
                                    <span class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                                        {{ $compareRows[$key][0] ?? '' }} {{ $name }}
                                        @if($key === 'prumo')<span class="badge-green">Recomendado</span>@endif
                                    </span>
                                    <span class="mt-0.5 block text-xs text-slate-500">{{ $desc }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-field-error name="strategy" />

                    <x-money-input name="budget" :value="$budget" label="Quanto posso pagar por mês" class="mt-5" required
                                   help="Sugestão do Prumo com base na sua renda e gastos: {{ brl($suggested) }}. O mínimo para manter tudo em dia é {{ brl($minTotal) }}." />

                    @if($essentialsAtRisk)
                        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800" role="alert">
                            ⚠️ Este valor invade suas despesas essenciais: renda {{ brl($income) }} − gastos fixos {{ brl($essentials) }} = no máximo <b>{{ brl($maxSafeBudget) }}</b> sem deixar de pagar moradia, contas e comida. Um plano que corta o essencial costuma gerar novas dívidas — negocie parcelas menores.
                        </div>
                    @endif

                    <button class="btn-primary mt-5 w-full">Recalcular meu plano</button>
                    <p class="mt-3 text-[11px] text-slate-400">Simulação com juros compostos mensais sobre o saldo informado. Resultados dependem de manter os pagamentos e de não criar novas dívidas — não há garantia.</p>
                </form>

                {{-- Resultado --}}
                <div class="space-y-6 @4xl:col-span-3">
                    @if($snapshots->count() >= 2)
                        @php $firstSnap = $snapshots->first(); $lastSnap = $snapshots->last(); $drop = $firstSnap->total_balance - $lastSnap->total_balance; @endphp
                        <div class="card card-pad">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="card-title">🧭 Sua evolução no GPS</h3>
                                <span class="{{ $drop >= 0 ? 'badge-green' : 'badge-red' }}">{{ $drop >= 0 ? '−'.brl($drop) : '+'.brl(abs($drop)) }} desde {{ $firstSnap->date->format('d/m/Y') }}</span>
                            </div>
                            <div class="mt-3 h-44">
                                <x-chart :config="[
                                    'type' => 'line',
                                    'data' => ['labels' => $snapshots->map(fn ($x) => $x->date->format('d/m'))->all(), 'datasets' => [[
                                        'label' => 'Saldo devedor', 'data' => $snapshots->pluck('total_balance')->all(), 'borderColor' => '#e11d48', 'backgroundColor' => '#e11d4818', 'fill' => true, 'tension' => 0.3, 'pointRadius' => 2,
                                    ]]],
                                    'options' => ['plugins' => ['legend' => ['display' => false]]],
                                ]" aria-label="Evolução do saldo devedor" />
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Juros mensais hoje: {{ brl($lastSnap->monthly_interest) }} (eram {{ brl($firstSnap->monthly_interest) }}).</p>
                        </div>
                    @endif
                    @if($plan)
                        @if($plan['feasible'] && isset($plan['free_date']))
                            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-50 via-white to-brand-50 p-6 ring-1 ring-brand-100">
                                <div class="pointer-events-none absolute -right-4 -top-4 text-8xl opacity-10">🕊️</div>
                                <div class="text-[13px] font-semibold uppercase tracking-wider text-brand-700">Sua data da liberdade</div>
                                <div class="mt-1 font-display text-4xl font-bold capitalize tracking-tight text-slate-900 sm:text-5xl">
                                    {{ month_name($plan['free_date']) }} de {{ $plan['free_date']->year }}
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    <div class="rounded-2xl bg-white/80 p-3 ring-1 ring-slate-100">
                                        <div class="text-xs text-slate-500">Tempo</div>
                                        <div class="font-display text-xl font-bold tabular text-slate-900">{{ $plan['months'] }} {{ $plan['months'] === 1 ? 'mês' : 'meses' }}</div>
                                    </div>
                                    <div class="rounded-2xl bg-white/80 p-3 ring-1 ring-slate-100">
                                        <div class="text-xs text-slate-500">Juros no caminho</div>
                                        <div class="font-display text-xl font-bold tabular text-rose-600">{{ brl($plan['interest']) }}</div>
                                    </div>
                                    @if($minimumPlan && $minInterest !== null && (! $minimumPlan['feasible'] || $minInterest > $plan['interest']))
                                        <div class="col-span-2 rounded-2xl bg-emerald-600 p-3 text-white sm:col-span-1">
                                            <div class="text-xs text-emerald-100">Economia vs. só o mínimo</div>
                                            <div class="font-display text-xl font-bold tabular">
                                                @if($minimumPlan['feasible']) {{ brl($minInterest - $plan['interest']) }} @else Enorme @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                @if(! empty($plan['order']))
                                    <div class="mt-4">
                                        <div class="mb-1.5 text-xs font-semibold text-slate-500">Ordem de quitação</div>
                                        <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                            @foreach($plan['order'] as $i => $name)
                                                <span class="rounded-full bg-white px-2.5 py-1 font-medium text-slate-700 ring-1 ring-slate-200">{{ $i + 1 }}. {{ $name }}</span>
                                                @if(! $loop->last)<span class="text-slate-300">→</span>@endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="rounded-3xl border border-rose-200 bg-rose-50 p-6">
                                <div class="flex items-start gap-3">
                                    <span class="text-3xl">⚠️</span>
                                    <div>
                                        <h3 class="font-display text-lg font-bold text-rose-900">Com esse valor, a dívida não para de crescer</h3>
                                        <p class="mt-1 text-sm text-rose-800">
                                            Os juros ({{ brl($monthlyInterest) }}/mês) estão consumindo o que você paga. Não é falta de esforço — é a taxa que está alta demais.
                                        </p>
                                        <ul class="mt-3 space-y-1 text-sm text-rose-800">
                                            <li>• Tente aumentar o valor mensal, mesmo que um pouco.</li>
                                            <li>• Negocie as dívidas de juros mais altos — use o <strong>Roteiro de negociação</strong> em cada dívida abaixo.</li>
                                            <li>• Troque dívidas caras por uma mais barata (ex.: consignado no lugar do rotativo).</li>
                                            <li>• Se nada couber, a Lei do Superendividamento (14.181/2021) existe para isso: procure o Procon ou a Defensoria.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($chart)
                            <div class="card card-pad">
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="card-title">Seu saldo devedor caindo</h3>
                                    <span class="text-xs text-slate-400">projeção mês a mês</span>
                                </div>
                                <div class="h-64"><x-chart :config="$chart" /></div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Comparativo --}}
            @if($comparison)
                <div class="card mb-6">
                    <div class="card-pad pb-0">
                        <h3 class="card-title">Comparando estratégias</h3>
                        <p class="muted text-sm">Com {{ brl($budget) }} por mês. Pagar só o mínimo é o caminho mais caro.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table mt-3">
                            <thead>
                            <tr>
                                <th>Estratégia</th>
                                <th class="text-right">Tempo</th>
                                <th class="text-right">Juros totais</th>
                                <th class="text-right">Economia</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($compareRows as $key => [$icon, $label])
                                @continue(! isset($comparison[$key]))
                                @php $c = $comparison[$key]; $isCurrent = $key === $strategy; @endphp
                                <tr class="{{ $isCurrent ? 'bg-brand-50/70' : '' }}">
                                    <td class="whitespace-nowrap font-medium">
                                        {{ $icon }} {{ $label }}
                                        @if($isCurrent)<span class="badge-green ml-1">Seu plano</span>@endif
                                    </td>
                                    <td class="whitespace-nowrap text-right tabular">
                                        @if($c['feasible'] && $c['months'] !== null)
                                            {{ $c['months'] }} {{ $c['months'] === 1 ? 'mês' : 'meses' }}
                                        @else
                                            <span class="badge-red">Não quita</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap text-right tabular money-neg">{{ brl($c['interest']) }}{{ $c['feasible'] ? '' : '+' }}</td>
                                    <td class="whitespace-nowrap text-right tabular">
                                        @if($key === 'minimum')
                                            <span class="text-slate-400">—</span>
                                        @elseif($c['feasible'] && $minimumPlan && $minimumPlan['feasible'] && $minInterest > $c['interest'])
                                            <span class="money-pos font-semibold">{{ brl($minInterest - $c['interest']) }}</span>
                                        @elseif($c['feasible'] && $minimumPlan && ! $minimumPlan['feasible'])
                                            <span class="money-pos font-semibold">Sai das dívidas</span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Dívidas ativas --}}
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-display text-lg font-semibold text-slate-900">Suas dívidas</h3>
                <span class="text-xs text-slate-500">Ordenadas por urgência</span>
            </div>
            <div class="mb-8 grid gap-4 md:grid-cols-2 @5xl:grid-cols-3">
                @foreach($active as $debt)
                    <div class="card card-pad flex flex-col {{ $debt->is_overdue ? 'ring-1 ring-rose-200' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-display text-base font-semibold text-slate-900">{{ $debt->creditor }}</div>
                                <div class="text-xs text-slate-500">{{ $debt->typeLabel() }}</div>
                            </div>
                            <div class="flex shrink-0 flex-wrap justify-end gap-1">
                                @if($debt->is_overdue)<span class="badge-red">Em atraso</span>@endif
                                @if($debt->status === 'negotiating')<span class="badge-sky">Negociando</span>@endif
                                @if($debt->is_shared)<span class="badge-violet">Casal</span>@endif
                            </div>
                        </div>

                        <div class="mt-4 font-display text-2xl font-bold tabular text-slate-900">{{ brl($debt->current_balance) }}</div>
                        <div class="mt-2">
                            <x-progress :value="$debt->progress()" color="bg-emerald-500" />
                            <div class="mt-1 flex justify-between text-[11px] text-slate-500">
                                <span>{{ pct($debt->progress(), 0) }} quitado</span>
                                @if((float) $debt->original_amount > 0)<span class="tabular">de {{ brl($debt->original_amount) }}</span>@endif
                            </div>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-xs text-slate-500">Juros</dt>
                                <dd class="font-semibold tabular {{ (float) $debt->monthly_interest_rate > 5 ? 'text-rose-600' : 'text-slate-800' }}">{{ num($debt->monthly_interest_rate) }}% a.m.</dd>
                                <dd class="text-[11px] text-slate-400 tabular">{{ num($debt->annualRate(), 1) }}% ao ano</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Parcela mínima</dt>
                                <dd class="font-semibold tabular text-slate-800">{{ brl($debt->minimum_payment) }}</dd>
                                @if($debt->due_day)<dd class="text-[11px] text-slate-400">vence dia {{ $debt->due_day }}</dd>@endif
                            </div>
                        </dl>

                        @if($debt->monthlyInterestCost() > 0)
                            <div class="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                🔥 Gera <strong class="tabular">{{ brl($debt->monthlyInterestCost()) }}</strong> de juros por mês
                            </div>
                        @endif

                        <div class="mt-auto pt-4">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn-primary btn-sm flex-1" @click="$dispatch('open-modal', 'pay-{{ $debt->id }}')">💸 Registrar pagamento</button>
                                <a href="{{ route('debts.negotiation', $debt) }}" class="btn-secondary btn-sm flex-1">🤝 Roteiro de negociação</a>
                            </div>
                            <div class="mt-2 flex justify-end gap-1">
                                <a href="{{ route('debts.edit', $debt) }}" class="btn-ghost btn-sm">Editar</a>
                                <form method="POST" action="{{ route('debts.destroy', $debt) }}" onsubmit="return confirm('Remover esta dívida? Os pagamentos já lançados continuam no histórico.')">
                                    @csrf @method('DELETE')
                                    <button class="btn-ghost btn-sm text-rose-600 hover:text-rose-700">Excluir</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <x-modal name="pay-{{ $debt->id }}" title="Pagamento — {{ $debt->creditor }}">
                        <form method="POST" action="{{ route('debts.pay', $debt) }}" class="space-y-4">
                            @csrf
                            <x-money-input name="amount" :id="'pay-amount-'.$debt->id" :value="$debt->minimum_payment > 0 ? min((float) $debt->minimum_payment, (float) $debt->current_balance) : null" label="Valor pago" required
                                           help="Saldo atual: {{ brl($debt->current_balance) }}" />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="label" for="pay-date-{{ $debt->id }}">Data</label>
                                    <input id="pay-date-{{ $debt->id }}" type="date" name="date" value="{{ now()->toDateString() }}" class="input" required>
                                </div>
                                <div>
                                    <label class="label" for="pay-acc-{{ $debt->id }}">Saiu da conta</label>
                                    <select id="pay-acc-{{ $debt->id }}" name="account_id" class="input">
                                        <option value="">— Não informar —</option>
                                        @foreach($accounts as $acc)
                                            <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <p class="help">O pagamento vira um lançamento em "Dívidas e juros" e o saldo da dívida é atualizado.</p>
                            <div class="flex justify-end gap-2 pt-1">
                                <button type="button" class="btn-secondary" @click="open = false">Cancelar</button>
                                <button class="btn-primary">Registrar</button>
                            </div>
                        </form>
                    </x-modal>
                @endforeach
            </div>
        @endif

        {{-- Conquistas --}}
        @if($paid->isNotEmpty())
            <div class="rounded-3xl bg-gradient-to-br from-emerald-50 via-white to-amber-50 p-5 ring-1 ring-emerald-100 sm:p-6">
                <div class="mb-4 flex items-center gap-3">
                    <span class="text-3xl">🏆</span>
                    <div>
                        <h3 class="font-display text-lg font-bold text-slate-900">Conquistas</h3>
                        <p class="text-sm text-slate-500">
                            {{ $paid->count() }} {{ $paid->count() === 1 ? 'dívida quitada' : 'dívidas quitadas' }} — {{ brl($paid->sum(fn ($d) => (float) $d->original_amount)) }} a menos pesando na sua vida.
                        </p>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 @4xl:grid-cols-3">
                    @foreach($paid as $debt)
                        <div class="flex items-center gap-3 rounded-2xl bg-white p-3.5 shadow-sm ring-1 ring-emerald-100">
                            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 text-lg">✅</div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold text-slate-800">{{ $debt->creditor }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ $debt->typeLabel() }}
                                    @if($debt->paid_off_at) · quitada em {{ month_name($debt->paid_off_at, true) }}/{{ $debt->paid_off_at->year }} @endif
                                </div>
                            </div>
                            @if((float) $debt->original_amount > 0)
                                <div class="text-right text-sm font-semibold tabular text-emerald-600">{{ brl($debt->original_amount) }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</x-layouts.app>
