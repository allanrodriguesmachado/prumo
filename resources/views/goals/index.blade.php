@php
    $types = \App\Models\Goal::TYPES;
    $open = $goals->whereNull('achieved_at');
    $achieved = $goals->whereNotNull('achieved_at');
    $hasEmergency = $goals->contains('type', 'emergency');
    $isCouple = household()->isCouple();
@endphp

<x-layouts.app title="Metas">
    <x-page-header title="Metas" subtitle="Sonhos com valor e data viram planos. Um aporte de cada vez.">
        <x-slot:actions>
            <a href="{{ route('goals.create') }}" class="btn-primary">+ Nova meta</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Criação rápida --}}
    <div class="-mx-4 mb-6 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
        @foreach($types as $key => [$label, $icon])
            <a href="{{ route('goals.create', ['tipo' => $key]) }}"
               class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800">
                <span>{{ $icon }}</span> {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Sugestão de reserva de emergência --}}
    @if(! $hasEmergency)
        <div class="mb-6 flex flex-col gap-4 rounded-3xl hero p-5 text-white shadow-lg sm:flex-row sm:items-center sm:p-6">
            <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-white/15 text-3xl">🛟</div>
            <div class="min-w-0 flex-1">
                <div class="text-xs font-semibold uppercase tracking-wider text-brand-200">Primeiro passo recomendado</div>
                <h3 class="font-display text-lg font-bold">Comece pela reserva de emergência</h3>
                <p class="mt-1 text-sm text-brand-100">
                    É o colchão que evita novas dívidas quando o imprevisto chega. Pelo seu custo de vida, o ideal é cerca de
                    <strong class="tabular text-white">{{ brl($emergencyTarget) }}</strong> (6 meses de gastos essenciais). Comece com qualquer valor.
                </p>
            </div>
            <a href="{{ route('goals.create', ['tipo' => 'emergency']) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-brand-800 hover:bg-brand-50">Criar minha reserva</a>
        </div>
    @endif

    @if($goals->isEmpty())
        <div class="card">
            <x-empty icon="🎯" title="Nenhuma meta ainda" text="Uma viagem, a casa própria, trocar de carro, a faculdade... Dê nome e valor ao seu sonho e o Prumo calcula quanto guardar por mês.">
                <a href="{{ route('goals.create') }}" class="btn-primary">+ Criar minha primeira meta</a>
            </x-empty>
        </div>
    @endif

    @if($open->isNotEmpty())
        <div class="mb-4 flex flex-col gap-2 rounded-2xl border p-4 text-sm sm:flex-row sm:items-center sm:justify-between {{ $plan['overbooked'] ? 'border-amber-200 bg-amber-50/70' : 'border-emerald-200 bg-emerald-50/60' }}">
            <div>
                <span class="font-semibold text-slate-900">Capacidade de poupança: {{ brl($plan['capacity']) }}/mês</span>
                <span class="text-slate-600">— é o que sobra, em média, entre sua renda e seus gastos dos últimos 3 meses.</span>
            </div>
            <div class="shrink-0 font-semibold {{ $plan['overbooked'] ? 'text-amber-800' : 'text-emerald-700' }}">
                Metas pedem {{ brl($plan['needed']) }}/mês {{ $plan['overbooked'] ? '— acima do que cabe' : '— cabe no orçamento ✓' }}
            </div>
        </div>
        <div class="mb-8 grid gap-4 sm:grid-cols-2 @5xl:grid-cols-3">
            @foreach($open as $goal)
                @php
                    $progress = $goal->progress();
                    $monthly = $goal->monthlyNeeded();
                    $late = $goal->target_date && $goal->target_date->isPast();
                @endphp
                <div class="card card-pad flex flex-col">
                    <div class="flex items-start gap-3">
                        <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-brand-50 text-2xl">{{ $goal->icon ?: '🎯' }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-display text-base font-semibold text-slate-900">{{ $goal->name }}</div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-1 text-xs text-slate-500">
                                <span>{{ $types[$goal->type][0] ?? 'Meta' }}</span>
                                @if($goal->is_shared)<span class="badge-violet">💑 Casal</span>@endif
                                @if($isCouple && ! $goal->is_shared && $goal->user)<span class="badge-slate">{{ $goal->user->name }}</span>@endif
                            </div>
                        </div>
                        <div class="font-display text-lg font-bold tabular {{ $progress >= 75 ? 'text-emerald-600' : 'text-slate-900' }}">{{ pct($progress, 0) }}</div>
                    </div>

                    <div class="mt-4">
                        <x-progress :value="$progress" color="{{ $progress >= 75 ? 'bg-emerald-500' : 'bg-brand-500' }}" />
                        <div class="mt-1.5 flex items-baseline justify-between text-sm">
                            <span class="font-semibold tabular text-slate-900">{{ brl($goal->current_amount) }}</span>
                            <span class="text-xs tabular text-slate-500">de {{ brl($goal->target_amount) }}</span>
                        </div>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-slate-500">Falta</dt>
                            <dd class="font-semibold tabular text-slate-800">{{ brl($goal->remaining()) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Prazo</dt>
                            <dd class="font-semibold {{ $late ? 'text-amber-600' : 'text-slate-800' }}">
                                @if($goal->target_date)
                                    {{ month_name($goal->target_date, true) }}/{{ $goal->target_date->year }}
                                @else
                                    <span class="font-normal text-slate-400">Sem data</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if($monthly)
                        <div class="mt-3 rounded-xl {{ $late ? 'bg-amber-50 text-amber-800' : 'bg-brand-50 text-brand-800' }} px-3 py-2 text-xs">
                            @if($late)
                                ⏰ O prazo passou — que tal definir uma nova data?
                            @else
                                📅 Guarde <strong class="tabular">{{ brl($monthly) }}</strong> por mês para chegar lá
                            @endif
                        </div>
                    @endif

                    @if($ev = $plan['goals'][$goal->id] ?? null)
                        @php $evBadge = ['emerald' => 'badge-green', 'amber' => 'badge-amber', 'rose' => 'badge-red', 'slate' => 'badge-slate'][$ev['color']]; @endphp
                        <div class="mt-3 text-xs text-slate-600"><span class="{{ $evBadge }}">{{ $ev['status'] }}</span> {{ $ev['text'] }}</div>
                    @endif

                    <div class="mt-auto pt-4">
                        <button type="button" class="btn-primary btn-sm w-full" @click="$dispatch('open-modal', 'contribute-{{ $goal->id }}')">💰 Aportar</button>
                        <div class="mt-2 flex justify-end gap-1">
                            <a href="{{ route('goals.edit', $goal) }}" class="btn-ghost btn-sm">Editar</a>
                            <form method="POST" action="{{ route('goals.destroy', $goal) }}" onsubmit="return confirm('Remover esta meta? Os aportes já lançados continuam no histórico.')">
                                @csrf @method('DELETE')
                                <button class="btn-ghost btn-sm text-rose-600 hover:text-rose-700">Excluir</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Metas alcançadas --}}
    @if($achieved->isNotEmpty())
        <div class="rounded-3xl bg-gradient-to-br from-amber-50 via-white to-emerald-50 p-5 ring-1 ring-amber-100 sm:p-6">
            <div class="mb-4 flex items-center gap-3">
                <span class="text-3xl">🏆</span>
                <div>
                    <h3 class="font-display text-lg font-bold text-slate-900">Metas alcançadas</h3>
                    <p class="text-sm text-slate-500">{{ brl($achieved->sum(fn ($g) => (float) $g->current_amount)) }} guardados com disciplina. Orgulho!</p>
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 @4xl:grid-cols-3">
                @foreach($achieved as $goal)
                    <div class="flex items-center gap-3 rounded-2xl bg-white p-3.5 shadow-sm ring-1 ring-amber-100">
                        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-amber-100 text-xl">{{ $goal->icon ?: '🎯' }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold text-slate-800">{{ $goal->name }}</div>
                            <div class="text-xs text-slate-500">
                                ✨ Alcançada em {{ month_name($goal->achieved_at, true) }}/{{ $goal->achieved_at->year }}
                                @if($goal->is_shared) · 💑 @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-semibold tabular text-emerald-600">{{ brl($goal->target_amount) }}</div>
                            <button type="button" class="text-[11px] font-medium text-slate-400 hover:text-brand-700" @click="$dispatch('open-modal', 'contribute-{{ $goal->id }}')">Movimentar</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Modais de aporte/resgate --}}
    @foreach($goals as $goal)
        <x-modal name="contribute-{{ $goal->id }}" title="{{ $goal->icon }} {{ $goal->name }}">
            <form method="POST" action="{{ route('goals.contribute', $goal) }}" class="space-y-4" x-data="{ withdraw: false }">
                @csrf
                <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 text-sm font-semibold">
                    <button type="button" @click="withdraw = false" class="rounded-lg py-2 transition" :class="!withdraw ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500'">💰 Aportar</button>
                    <button type="button" @click="withdraw = true" class="rounded-lg py-2 transition" :class="withdraw ? 'bg-white text-amber-700 shadow-sm' : 'text-slate-500'">↩️ Resgatar</button>
                </div>
                <input type="hidden" name="withdraw" :value="withdraw ? 1 : 0">

                <x-money-input name="amount" :id="'goal-amount-'.$goal->id" :value="$goal->monthlyNeeded()" label="Valor" required
                               help="Guardado: {{ brl($goal->current_amount) }} · Falta: {{ brl($goal->remaining()) }}" />

                <div>
                    <label class="label" for="goal-acc-{{ $goal->id }}" x-text="withdraw ? 'Vai para a conta' : 'Sai da conta'">Sai da conta</label>
                    <select id="goal-acc-{{ $goal->id }}" name="account_id" class="input">
                        <option value="">— Não informar —</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <label class="flex items-start gap-2 text-sm text-slate-600">
                    <input type="checkbox" class="checkbox mt-0.5" x-model="withdraw">
                    <span>Resgatar dinheiro desta meta (retirar)</span>
                </label>

                <p x-show="withdraw" x-cloak class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    Tudo bem precisar usar — é para isso que a reserva existe. Registramos como entrada na conta escolhida.
                </p>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" class="btn-secondary" @click="open = false">Cancelar</button>
                    <button class="btn-primary" x-text="withdraw ? 'Registrar resgate' : 'Registrar aporte'">Registrar aporte</button>
                </div>
            </form>
        </x-modal>
    @endforeach
</x-layouts.app>
