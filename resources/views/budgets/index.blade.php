@php
    $groups = \App\Models\Category::GROUPS;
    $ruleMeta = [
        'needs' => ['Essencial', 'bg-sky-500', 'text-sky-700', '🏠'],
        'wants' => ['Estilo de vida', 'bg-violet-500', 'text-violet-700', '🎉'],
        'savings' => ['Futuro', 'bg-emerald-500', 'text-emerald-700', '🌱'],
    ];
    $ruleTotalPct = max(100, array_sum(array_map(fn ($g) => $g['pct'], $rule)));
    $byGroup = $categories->groupBy(fn ($c) => $c->group ?: 'wants');
    $budgetTotal = (float) $categories->sum(fn ($c) => (float) $c->monthly_budget);
    $savingPct = (int) old('saving_target_pct', household()->setting('saving_target_pct', 10));
    $isCurrent = $month->isSameMonth(now());
@endphp
<x-layouts.app title="Orçamentos">
    <x-page-header title="Orçamentos" subtitle="Defina um teto por categoria e acompanhe o mês sem sustos.">
        <x-slot:actions>
            <x-month-nav :month="$month" route="budgets.index" />
        </x-slot:actions>
    </x-page-header>

    {{-- Regra 50/30/20 --}}
    <div class="card card-pad">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="card-title">Regra 50/30/20 em <span class="capitalize">{{ month_name($month) }}</span></h3>
            <span class="text-xs text-slate-500">Percentuais sobre o que entrou no mês</span>
        </div>
        <p class="mt-1 text-sm text-slate-500">
            Um guia simples: <strong>50%</strong> da renda para o essencial, <strong>30%</strong> para o estilo de vida e <strong>20%</strong> para o futuro (quitar dívidas, reserva e investimentos).
            O que sobra sem gastar conta como futuro. Se estiver longe disso, tudo bem — ajuste aos poucos.
        </p>

        <div class="mt-5">
            <div class="flex h-4 w-full overflow-hidden rounded-full bg-slate-100">
                @foreach($ruleMeta as $k => [$label, $bg])
                    @php $w = ($rule[$k]['pct'] ?? 0) / $ruleTotalPct * 100; @endphp
                    @if($w > 0)<div class="{{ $bg }} h-full" style="width: {{ $w }}%" title="{{ $label }}: {{ pct($rule[$k]['pct']) }}"></div>@endif
                @endforeach
            </div>
            {{-- marcadores do ideal (50% e 80%) --}}
            <div class="relative mt-1 h-3 text-[10px] text-slate-400">
                <span class="absolute -translate-x-1/2" style="left: {{ 50 / $ruleTotalPct * 100 }}%">▲ 50</span>
                <span class="absolute -translate-x-1/2" style="left: {{ 80 / $ruleTotalPct * 100 }}%">▲ 80</span>
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            @foreach($ruleMeta as $k => [$label, $bg, $text, $icon])
                @php
                    $r = $rule[$k] ?? ['total' => 0, 'pct' => 0, 'ideal' => 0];
                    $ok = $k === 'savings' ? $r['pct'] >= $r['ideal'] : $r['pct'] <= $r['ideal'];
                @endphp
                <div class="rounded-xl border border-slate-100 p-3">
                    <div class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <span class="h-2.5 w-2.5 rounded-full {{ $bg }}"></span> {{ $icon }} {{ $label }}
                    </div>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="font-display text-xl font-bold tabular {{ $text }}">{{ pct($r['pct'], 0) }}</span>
                        <span class="text-xs text-slate-400">ideal {{ $k === 'savings' ? '≥' : '≤' }} {{ $r['ideal'] }}%</span>
                    </div>
                    <div class="mt-0.5 flex items-center justify-between text-xs">
                        <span class="tabular text-slate-500">{{ brl($r['total']) }}</span>
                        <span class="{{ $ok ? 'text-emerald-600' : 'text-amber-600' }}">{{ $ok ? '✓ No caminho' : 'Dá para ajustar' }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Formulário de orçamentos --}}
    <form method="POST" action="{{ route('budgets.update') }}" class="mt-6"
          x-data="{
              total: @js(round($budgetTotal, 2)),
              income: @js((float) $income),
              recalc() {
                  this.total = [...this.$root.querySelectorAll('[data-budget]')].reduce((s, el) => s + (Number((el.value || '0').replace(/\./g, '').replace(',', '.')) || 0), 0);
              },
              fmt(v) { return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) },
          }">
        @csrf

        <div class="card card-pad">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-md">
                    <label class="label" for="f-saving">Meta de poupança (% da renda)</label>
                    <div class="relative w-32">
                        <input id="f-saving" type="number" name="saving_target_pct" min="0" max="80" step="1" value="{{ $savingPct }}" class="input pr-8 tabular">
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-sm text-slate-400">%</span>
                    </div>
                    <p class="help">Essa parte da renda fica separada no cálculo de <strong>“quanto posso gastar hoje”</strong> — guardar primeiro, gastar depois. Comece com 5% ou 10% se estiver apertado.</p>
                    <x-field-error name="saving_target_pct" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" form="suggest-form" class="btn-secondary"
                            onclick="return confirm('Sugerir orçamentos com base na média dos últimos 3 meses? Os valores atuais das categorias com histórico serão substituídos.')">
                        🪄 Sugerir orçamentos automaticamente
                    </button>
                </div>
            </div>
        </div>

        @if($categories->isEmpty())
            <div class="card mt-4">
                <x-empty icon="🏷️" title="Nenhuma categoria de despesa" text="Crie categorias para definir orçamentos.">
                    <a href="{{ route('categories.create', ['tipo' => 'expense']) }}" class="btn-primary">+ Nova categoria</a>
                </x-empty>
            </div>
        @else
            <div class="mt-4 space-y-4">
                @foreach($groups as $gKey => $gLabel)
                    @continue(! isset($byGroup[$gKey]))
                    <section class="card">
                        <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3 sm:px-6">
                            <span class="h-2.5 w-2.5 rounded-full {{ $ruleMeta[$gKey][1] ?? 'bg-slate-400' }}"></span>
                            <h3 class="card-title">{{ $gLabel }}</h3>
                        </div>
                        <ul class="divide-y divide-slate-100">
                            @foreach($byGroup[$gKey] as $cat)
                                @php
                                    $s = (float) ($spent[$cat->id] ?? 0);
                                    $av = (float) ($avg[$cat->id] ?? 0);
                                    $b = (float) $cat->monthly_budget;
                                    $p = $b > 0 ? $s / $b * 100 : 0;
                                    $ahead = $b > 0 && $isCurrent && $p > $elapsed + 10 && $p < 100;
                                @endphp
                                <li class="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_160px] sm:items-center sm:px-6">
                                    <div class="min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex min-w-0 items-center gap-2">
                                                <span class="text-lg">{{ $cat->icon }}</span>
                                                <span class="truncate font-medium text-slate-800">{{ $cat->name }}</span>
                                                @if($b > 0 && $p >= 100)<span class="badge-red">Estourou</span>
                                                @elseif($ahead)<span class="badge-amber">Ritmo acelerado</span>@endif
                                            </div>
                                            <span class="shrink-0 text-sm tabular">
                                                <span class="font-semibold text-slate-800">{{ brl($s) }}</span>
                                                @if($b > 0)<span class="text-slate-400"> / {{ brl($b) }}</span>@endif
                                            </span>
                                        </div>
                                        @if($b > 0)
                                            <div class="relative mt-2">
                                                <x-progress :value="$p" />
                                                @if($elapsed > 0 && $elapsed < 100)
                                                    <div class="absolute -top-1 h-4 w-0.5 rounded bg-slate-700/60" style="left: {{ $elapsed }}%" title="Hoje: {{ pct($elapsed, 0) }} do mês"></div>
                                                @endif
                                            </div>
                                        @endif
                                        <div class="mt-1.5 text-xs text-slate-500">
                                            Média 3 meses: <span class="tabular font-medium text-slate-600">{{ brl($av) }}</span>
                                            @if($b > 0)
                                                · {{ $s <= $b ? 'restam '.brl($b - $s) : 'passou '.brl($s - $b) }}
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <label class="sr-only" for="b-{{ $cat->id }}">Orçamento de {{ $cat->name }}</label>
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-medium text-slate-400">R$</span>
                                            <input id="b-{{ $cat->id }}" type="text" inputmode="decimal" name="budget[{{ $cat->id }}]" data-budget
                                                   value="{{ old('budget.'.$cat->id, $cat->monthly_budget ? num($cat->monthly_budget) : '') }}"
                                                   placeholder="{{ $av > 0 ? num(ceil($av / 10) * 10, 0) : 'Sem teto' }}"
                                                   @input="recalc()" class="input pl-9 text-right tabular font-semibold">
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>

            {{-- Totais --}}
            @php $leftover = $income - $budgetTotal; @endphp
            <div class="sticky bottom-0 z-10 mt-4 -mx-4 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border sm:shadow-lg">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <div class="text-[11px] uppercase tracking-wide text-slate-400">Orçado</div>
                            <div class="font-bold tabular text-slate-900" x-text="fmt(total)">{{ brl($budgetTotal) }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] uppercase tracking-wide text-slate-400">Renda mensal</div>
                            <div class="font-bold tabular money-pos">{{ brl($income) }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] uppercase tracking-wide text-slate-400">Sobra</div>
                            <div class="font-bold tabular" :class="income - total < 0 ? 'money-neg' : 'text-emerald-600'" x-text="fmt(income - total)">{{ brl($leftover) }}</div>
                        </div>
                    </div>
                    <button class="btn-primary">Salvar orçamentos</button>
                </div>
                <p x-cloak x-show="income > 0 && total > income" class="mt-2 text-xs text-rose-600">Os orçamentos somam mais do que a renda. Vale reduzir alguma categoria de estilo de vida.</p>
            </div>
        @endif
    </form>

    <form id="suggest-form" method="POST" action="{{ route('budgets.suggest') }}" class="hidden">@csrf</form>
</x-layouts.app>
