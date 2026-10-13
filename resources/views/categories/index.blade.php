@php
    $groupBadge = ['needs' => 'badge-sky', 'wants' => 'badge-violet', 'savings' => 'badge-green'];
@endphp
<x-layouts.app title="Categorias">
    <x-page-header title="Categorias" subtitle="Organize seus gastos e receitas do jeito que fizer sentido para você.">
        <x-slot:actions>
            <a href="{{ route('categories.create', ['tipo' => 'income']) }}" class="btn-secondary">+ Receita</a>
            <a href="{{ route('categories.create', ['tipo' => 'expense']) }}" class="btn-primary">+ Despesa</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 @3xl:grid-cols-2">
        @foreach([['income', 'Receitas', '💰', $income], ['expense', 'Despesas', '🧾', $expense]] as [$type, $title, $icon, $list])
            <section class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">{{ $icon }}</span>
                        <h3 class="card-title">{{ $title }}</h3>
                        <span class="badge-slate">{{ $list->count() }}</span>
                    </div>
                    <a href="{{ route('categories.create', ['tipo' => $type]) }}" class="btn-ghost btn-sm">+ Nova</a>
                </div>

                @if($list->isEmpty())
                    <x-empty :icon="$icon" title="Nenhuma categoria" text="Crie a primeira para começar a organizar." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($list as $cat)
                            <li class="flex items-center gap-3 px-5 py-3 sm:px-6 {{ $cat->archived ? 'opacity-55' : '' }}">
                                <span class="relative grid h-10 w-10 shrink-0 place-items-center rounded-xl text-lg" style="background-color: {{ $cat->color }}1f">
                                    {{ $cat->icon }}
                                    <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full ring-2 ring-white" style="background-color: {{ $cat->color }}"></span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="truncate font-medium text-slate-800">{{ $cat->name }}</span>
                                        @if($type === 'expense')
                                            <span class="{{ $groupBadge[$cat->group] ?? 'badge-slate' }}">{{ \App\Models\Category::GROUPS[$cat->group] ?? $cat->group }}</span>
                                        @endif
                                        @if($cat->archived)<span class="badge-amber">Arquivada</span>@endif
                                    </div>
                                    <div class="mt-0.5 text-xs text-slate-500">
                                        {{ $cat->transactions_count }} {{ $cat->transactions_count === 1 ? 'lançamento' : 'lançamentos' }}
                                        @if($cat->monthly_budget)
                                            · orçamento <span class="font-medium tabular text-slate-700">{{ brl($cat->monthly_budget) }}</span>/mês
                                        @endif
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-0.5">
                                    <a href="{{ route('categories.edit', $cat) }}" class="btn-ghost btn-sm" title="Editar">✏️</a>
                                    <form method="POST" action="{{ route('categories.destroy', $cat) }}"
                                          onsubmit="return confirm('{{ $cat->transactions_count ? 'Esta categoria tem lançamentos e será arquivada. Continuar?' : 'Excluir esta categoria?' }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn-ghost btn-sm text-rose-600" title="Excluir">🗑️</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>

    <p class="mt-6 text-center text-xs text-slate-400">
        Os grupos <strong>Essencial</strong>, <strong>Estilo de vida</strong> e <strong>Futuro</strong> alimentam a regra 50/30/20 em
        <a href="{{ route('budgets.index') }}" class="font-medium text-brand-700 hover:underline">Orçamentos</a>.
    </p>
</x-layouts.app>
