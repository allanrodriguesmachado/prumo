@php
    $c = $category;
    $editing = $c->exists;
    $emojis = ['🏠', '💡', '💧', '🔥', '📶', '🛒', '🍎', '🍔', '🍕', '☕', '🍺', '🚌', '🚗', '⛽', '🅿️', '✈️', '🩺', '💊', '🦷', '🏋️', '📚', '🎓', '🧸', '🐾', '👕', '🛍️', '💅', '💇', '🎉', '🎮', '🎬', '📺', '🎵', '🎁', '📱', '💻', '🧾', '🏦', '⛓️', '🎯', '🌱', '📈', '💼', '✨', '🧑‍💻', '↩️', '💰', '🏷️', '📦', '❤️'];
    $groupHelp = [
        'needs' => ['🏠', 'Contas que não dá para cortar: moradia, mercado, transporte, saúde. Ideal: até 50% da renda.'],
        'wants' => ['🎉', 'O que deixa a vida gostosa: delivery, lazer, assinaturas, compras. Ideal: até 30%.'],
        'savings' => ['🌱', 'Dinheiro para o seu futuro: quitar dívidas, reserva, metas, investimentos. Ideal: pelo menos 20%.'],
    ];
@endphp
<x-layouts.app :title="$editing ? 'Editar categoria' : 'Nova categoria'">
    <x-page-header :title="$editing ? 'Editar categoria' : 'Nova categoria'" subtitle="Dê um nome, um ícone e uma cor — fica mais fácil de achar nos relatórios.">
        <x-slot:actions><a href="{{ route('categories.index') }}" class="btn-ghost">← Voltar</a></x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('categories.update', $c) : route('categories.store') }}" class="card card-pad max-w-2xl space-y-5"
          x-data="{
              type: @js(old('type', $c->type ?? 'expense')),
              icon: @js(old('icon', $c->icon ?? '🏷️')),
              color: @js(old('color', $c->color ?? '#0f766e')),
              name: @js(old('name', $c->name ?? '')),
              group: @js(old('group', $c->group ?? 'wants')),
              picker: false,
          }">
        @csrf
        @if($editing) @method('PUT') @endif
        <input type="hidden" name="icon" :value="icon">

        <div>
            <span class="label">Tipo</span>
            <div class="grid grid-cols-2 gap-2">
                @foreach(['expense' => '🧾 Despesa', 'income' => '💰 Receita'] as $k => $label)
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-medium transition"
                           :class="type === '{{ $k }}' ? 'border-brand-500 bg-brand-50 text-brand-800 ring-2 ring-brand-500/20' : 'border-slate-200 text-slate-600 hover:bg-slate-50'">
                        <input type="radio" name="type" value="{{ $k }}" x-model="type" class="sr-only"> {{ $label }}
                    </label>
                @endforeach
            </div>
            <x-field-error name="type" />
        </div>

        <div class="flex items-end gap-3">
            <div class="relative">
                <span class="label">Ícone</span>
                <button type="button" @click="picker = !picker" class="grid h-[42px] w-14 place-items-center rounded-xl border border-slate-300 text-2xl shadow-sm hover:bg-slate-50"
                        :style="`background-color: ${color}1f`" x-text="icon" aria-label="Escolher ícone"></button>
            </div>
            <div class="flex-1">
                <label class="label" for="f-name">Nome</label>
                <input id="f-name" name="name" x-model="name" class="input @error('name') input-error @enderror" required maxlength="60" placeholder="Ex.: Academia">
            </div>
        </div>
        <x-field-error name="name" />

        <div x-show="picker" x-collapse x-cloak class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <div class="grid grid-cols-8 gap-1 sm:grid-cols-10">
                @foreach($emojis as $e)
                    <button type="button" @click="icon = @js($e); picker = false" class="grid aspect-square place-items-center rounded-lg text-xl transition hover:bg-white hover:shadow-sm"
                            :class="icon === @js($e) ? 'bg-white ring-2 ring-brand-500' : ''">{{ $e }}</button>
                @endforeach
            </div>
            <div class="mt-3 flex items-center gap-2">
                <label for="f-icon" class="text-xs text-slate-500">Ou digite/cole um emoji:</label>
                <input id="f-icon" x-model="icon" maxlength="16" class="input w-24 py-1.5 text-center text-lg">
            </div>
        </div>
        <x-field-error name="icon" />

        <div>
            <span class="label">Cor</span>
            <div class="flex flex-wrap items-center gap-2">
                @foreach(['#0f766e', '#16a34a', '#0891b2', '#2563eb', '#7c3aed', '#9333ea', '#db2777', '#dc2626', '#ea580c', '#f59e0b', '#a16207', '#64748b'] as $sw)
                    <button type="button" @click="color = '{{ $sw }}'" class="h-8 w-8 rounded-full ring-offset-2 transition"
                            :class="color.toLowerCase() === '{{ $sw }}' ? 'ring-2 ring-slate-800' : 'hover:scale-110'"
                            style="background-color: {{ $sw }}" aria-label="Cor {{ $sw }}"></button>
                @endforeach
                <label class="ml-1 inline-flex h-8 cursor-pointer items-center gap-1 rounded-full border border-slate-200 px-2 text-xs text-slate-600 hover:bg-slate-50">
                    <input type="color" name="color" x-model="color" class="h-5 w-5 cursor-pointer rounded border-0 bg-transparent p-0"> Outra
                </label>
            </div>
            <x-field-error name="color" />
        </div>

        <div>
            <span class="label">Grupo (regra 50/30/20)</span>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach(\App\Models\Category::GROUPS as $k => $label)
                    <label class="flex cursor-pointer flex-col rounded-xl border p-3 text-sm transition"
                           :class="group === '{{ $k }}' ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-500/20' : 'border-slate-200 hover:bg-slate-50'">
                        <input type="radio" name="group" value="{{ $k }}" x-model="group" class="sr-only">
                        <span class="font-semibold text-slate-800">{{ $groupHelp[$k][0] }} {{ $label }}</span>
                        <span class="mt-1 text-xs text-slate-500">{{ $groupHelp[$k][1] }}</span>
                    </label>
                @endforeach
            </div>
            <p class="help">A regra 50/30/20 é um guia simples: metade da renda para o essencial, 30% para o estilo de vida e 20% para o futuro. Não precisa ser perfeito — é uma bússola.</p>
            <x-field-error name="group" />
        </div>

        <div x-show="type === 'expense'">
            <x-money-input name="monthly_budget" :value="$c->monthly_budget" label="Orçamento mensal (opcional)"
                           help="Um teto para gastar nesta categoria por mês. Te avisamos quando estiver chegando perto." />
        </div>

        @if($editing)
            <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4">
                <input type="checkbox" name="archived" value="1" class="checkbox mt-0.5" @checked(old('archived', $c->archived))>
                <span class="text-sm"><span class="font-medium text-slate-800">Arquivar categoria</span>
                    <span class="block text-xs text-slate-500">Ela some das opções de lançamento, mas o histórico continua.</span></span>
            </label>
        @endif

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('categories.index') }}" class="btn-secondary">Cancelar</a>
            <button class="btn-primary">{{ $editing ? 'Salvar alterações' : 'Criar categoria' }}</button>
        </div>
    </form>
</x-layouts.app>
