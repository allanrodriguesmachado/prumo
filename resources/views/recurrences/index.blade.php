<x-layouts.app title="Renda & gastos fixos">
    <x-page-header title="Renda & gastos fixos" subtitle="Cadastre uma vez o que se repete todo mês — o Prumo cuida do resto.">
        <x-slot:actions>
            <a href="{{ route('recurrences.create', ['tipo' => 'salary']) }}" class="btn-secondary">💼 Salário</a>
            <a href="{{ route('recurrences.create', ['tipo' => 'fixed']) }}" class="btn-primary">+ Gasto fixo</a>
        </x-slot:actions>
    </x-page-header>

    @php
        $tone = $commitment < 50 ? 'green' : ($commitment < 70 ? 'amber' : 'red');
        $commitHint = $monthlyIncome <= 0
            ? 'Cadastre sua renda para calcular'
            : ($commitment < 50 ? 'Ótimo! Sobra espaço para o dia a dia e para poupar.' : ($commitment < 70 ? 'Atenção: o ideal é ficar abaixo de 50%.' : 'Os fixos estão pesando. Vale revisar contratos e assinaturas.'));
        $isCouple = household()->isCouple();
    @endphp

    <div class="stat-grid @3xl:grid-cols-4">
        <x-stat label="Renda mensal fixa" :value="brl($monthlyIncome)" tone="green" icon="💰" hint="Salários + rendas recorrentes" />
        <x-stat label="Gastos fixos por mês" :value="brl($monthlyFixed)" tone="red" icon="📌" hint="Valor mensal equivalente" />
        <x-stat label="Renda comprometida" :value="pct($commitment)" :tone="$tone" icon="⚖️" :hint="$commitHint">
            <x-progress class="mt-3" :value="$commitment" :color="['green' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'red' => 'bg-rose-500'][$tone]" />
        </x-stat>
        <x-stat label="Gasto médio mensal" :value="brl($avgDaily)" icon="📊" hint="Média dos últimos 3 meses" />
    </div>

    <div class="mt-5 flex items-start gap-3 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-900">
        <span class="text-lg">🔁</span>
        <p>Cada item recorrente gera <strong>lançamentos previstos</strong> automaticamente todo mês. Assim você enxerga com antecedência o que vai entrar e sair — e só precisa confirmar quando acontecer (ou deixar marcado como pago automaticamente).</p>
    </div>

    @php
        $sections = [
            ['salary', '💼', 'Salário fixo', 'Sua renda principal, que cai todo mês.', $salaries, 'income'],
            ['extra_income', '✨', 'Renda extra', 'Aluguel recebido, pensão, bicos frequentes...', $extras, 'income'],
            ['fixed', '📌', 'Gastos fixos', 'Aluguel, contas da casa, escola, assinaturas, financiamentos...', $fixed, 'expense'],
        ];
    @endphp

    <div class="mt-6 space-y-6">
        @foreach($sections as [$kind, $icon, $title, $desc, $list, $type])
            <section class="card">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl {{ $type === 'income' ? 'bg-emerald-50' : 'bg-rose-50' }} text-xl">{{ $icon }}</span>
                        <div>
                            <h3 class="card-title">{{ $title }}</h3>
                            <p class="text-xs text-slate-500">{{ $desc }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        @if($list->isNotEmpty())
                            <span class="text-sm font-semibold tabular {{ $type === 'income' ? 'money-pos' : 'money-neg' }}">
                                {{ brl($list->where('active', true)->sum(fn ($r) => $r->monthlyAmount())) }}<span class="text-xs font-normal text-slate-400">/mês</span>
                            </span>
                        @endif
                        <a href="{{ route('recurrences.create', ['tipo' => $kind]) }}" class="btn-secondary btn-sm">+ Adicionar</a>
                    </div>
                </div>

                @if($list->isEmpty())
                    <x-empty :icon="$icon" :title="'Nada em '.mb_strtolower($title).' ainda'"
                             :text="$type === 'income' ? 'Cadastre para o Prumo prever quanto entra no mês.' : 'Cadastre para saber quanto da sua renda já tem destino certo.'" class="py-10">
                        <a href="{{ route('recurrences.create', ['tipo' => $kind]) }}" class="btn-primary btn-sm">+ Adicionar</a>
                    </x-empty>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($list as $r)
                            <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:px-6 {{ $r->active ? '' : 'opacity-60' }}">
                                <div class="flex min-w-0 flex-1 items-start gap-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-lg"
                                          style="background-color: {{ ($r->category?->color ?? '#64748b') }}1a">{{ $r->category?->icon ?? ($type === 'income' ? '💰' : '📌') }}</span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="truncate font-semibold text-slate-800">{{ $r->description }}</span>
                                            @if($r->is_shared && $isCouple)<span class="badge-violet">💑 Compartilhado</span>@endif
                                            @if(! $r->active)<span class="badge-slate">Inativo</span>@endif
                                            @if($r->auto_paid)<span class="badge-sky" title="Marcado como pago/recebido automaticamente">⚡ Automático</span>@endif
                                        </div>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500">
                                            <span>{{ \App\Models\Recurrence::FREQUENCIES[$r->frequency] ?? $r->frequency }}</span>
                                            @if($r->frequency !== 'weekly')<span>· todo dia {{ $r->day_of_month }}</span>@endif
                                            @if($r->category)<span>· {{ $r->category->name }}</span>@endif
                                            @if($r->creditCard)<span>· 💳 {{ $r->creditCard->name }}</span>@elseif($r->account)<span>· 🏦 {{ $r->account->name }}</span>@endif
                                            @if($isCouple && $r->user)<span>· {{ $r->user->name }}</span>@endif
                                            @if($r->end_date)<span class="font-medium text-amber-700">· até {{ $r->end_date->format('m/Y') }}</span>@endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-4 sm:justify-end">
                                    <div class="text-right">
                                        <div class="font-semibold tabular {{ $type === 'income' ? 'money-pos' : 'money-neg' }}">{{ brl($r->amount) }}</div>
                                        @if($r->frequency !== 'monthly')
                                            <div class="text-[11px] text-slate-400 tabular">≈ {{ brl($r->monthlyAmount()) }}/mês</div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('recurrences.edit', $r) }}" class="btn-ghost btn-sm" title="Editar">✏️</a>
                                        <form method="POST" action="{{ route('recurrences.destroy', $r) }}" onsubmit="return confirm('Remover este item? Os lançamentos futuros não pagos serão apagados; os já pagos ficam no histórico.')">
                                            @csrf @method('DELETE')
                                            <button class="btn-ghost btn-sm text-rose-600" title="Excluir">🗑️</button>
                                        </form>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
</x-layouts.app>
