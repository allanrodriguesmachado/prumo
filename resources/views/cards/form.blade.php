@php
    $c = $card;
    $editing = $c->exists;
    $brands = ['Visa', 'Mastercard', 'Elo', 'Amex', 'Hipercard', 'Outra'];
    $swatches = ['#7c3aed', '#820ad1', '#1e293b', '#0f766e', '#2563eb', '#dc2626', '#ea580c', '#ec7000', '#db2777', '#ca8a04', '#64748b', '#000000'];
@endphp
<x-layouts.app :title="$editing ? 'Editar cartão' : 'Novo cartão'">
    <x-page-header :title="$editing ? 'Editar cartão' : 'Novo cartão'" subtitle="Com o fechamento e o vencimento certos, o Prumo coloca cada compra na fatura correta.">
        <x-slot:actions><a href="{{ route('cards.index') }}" class="btn-ghost">← Voltar</a></x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('cards.update', $c) : route('cards.store') }}" class="card card-pad max-w-2xl space-y-5"
          x-data="{ color: @js(old('color', $c->color ?? '#7c3aed')), name: @js(old('name', $c->name ?? '')), brand: @js(old('brand', $c->brand ?? '')), closing: @js((int) old('closing_day', $c->closing_day ?? 1)) }">
        @csrf
        @if($editing) @method('PUT') @endif

        {{-- Prévia --}}
        <div class="mx-auto aspect-[1.75] w-full max-w-xs overflow-hidden rounded-2xl p-5 text-white shadow-lg"
             :style="`background: linear-gradient(135deg, ${color} 0%, ${color}cc 55%, #0f172a 140%)`">
            <div class="flex h-full flex-col justify-between">
                <div class="flex items-start justify-between">
                    <div class="h-7 w-10 rounded-md bg-gradient-to-br from-amber-200 to-amber-400/80"></div>
                    <span class="text-sm font-bold italic opacity-90" x-text="brand || 'Crédito'"></span>
                </div>
                <div class="font-display text-lg font-semibold" x-text="name || 'Nome do cartão'"></div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="f-name">Nome</label>
                <input id="f-name" name="name" x-model="name" class="input @error('name') input-error @enderror" required maxlength="80" placeholder="Ex.: Nubank roxinho">
                <x-field-error name="name" />
            </div>
            <div>
                <label class="label" for="f-brand">Bandeira</label>
                <select id="f-brand" name="brand" x-model="brand" class="input">
                    <option value="">—</option>
                    @foreach($brands as $b)
                        <option value="{{ $b }}" @selected(old('brand', $c->brand) === $b)>{{ $b }}</option>
                    @endforeach
                </select>
                <x-field-error name="brand" />
            </div>
        </div>

        <x-money-input name="limit" :value="$c->limit ?? 0" label="Limite total" required help="O limite que o banco liberou para você." />

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="f-closing">Dia de fechamento</label>
                <input id="f-closing" type="number" name="closing_day" min="1" max="31" x-model.number="closing" class="input tabular @error('closing_day') input-error @enderror" required>
                <x-field-error name="closing_day" />
            </div>
            <div>
                <label class="label" for="f-due">Dia de vencimento</label>
                <input id="f-due" type="number" name="due_day" min="1" max="31" value="{{ old('due_day', $c->due_day) }}" class="input tabular @error('due_day') input-error @enderror" required>
                <x-field-error name="due_day" />
            </div>
        </div>
        <div class="-mt-2 flex gap-3 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-900">
            <span>🗓️</span>
            <p>O <strong>melhor dia de compra</strong> é o próprio dia do fechamento<span x-show="closing"> (dia <span x-text="closing"></span>)</span>: o que você comprar a partir dele só entra na fatura seguinte, ganhando quase 40 dias para pagar. Compras antes do fechamento caem na fatura atual.</p>
        </div>

        <div>
            <label class="label" for="f-account">Conta que paga a fatura</label>
            <select id="f-account" name="account_id" class="input">
                <option value="">— Escolher na hora de pagar —</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}" @selected((string) old('account_id', $c->account_id) === (string) $a->id)>🏦 {{ $a->name }}</option>
                @endforeach
            </select>
            <x-field-error name="account_id" />
        </div>

        <div>
            <span class="label">Cor</span>
            <div class="flex flex-wrap items-center gap-2">
                @foreach($swatches as $sw)
                    <button type="button" @click="color = '{{ $sw }}'" class="h-8 w-8 rounded-full ring-offset-2 transition"
                            :class="color.toLowerCase() === '{{ $sw }}' ? 'ring-2 ring-slate-800' : 'hover:scale-110'"
                            style="background-color: {{ $sw }}" aria-label="Cor {{ $sw }}"></button>
                @endforeach
                <label class="ml-1 inline-flex h-8 cursor-pointer items-center gap-1 rounded-full border border-slate-200 px-2 text-xs text-slate-600 hover:bg-slate-50">
                    <input type="color" name="color" x-model="color" class="h-5 w-5 cursor-pointer rounded border-0 bg-transparent p-0">
                    Outra
                </label>
            </div>
            <x-field-error name="color" />
        </div>

        @if(household()->isCouple() || $editing)
            <div class="space-y-3 rounded-xl bg-slate-50 p-4">
                @if(household()->isCouple())
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="is_shared" value="1" class="checkbox mt-0.5" @checked(old('is_shared', $c->is_shared))>
                        <span class="text-sm"><span class="font-medium text-slate-800">Cartão compartilhado do casal</span>
                            <span class="block text-xs text-slate-500">Os dois usam este cartão (ou a fatura é dividida).</span></span>
                    </label>
                    <label class="mt-3 flex items-start gap-3">
                        <input type="checkbox" name="is_private" value="1" class="checkbox mt-0.5" @checked(old('is_private', $c->is_private))>
                        <span class="text-sm"><span class="font-medium text-slate-800">🔒 Cartão privado</span>
                            <span class="block text-xs text-slate-500">Seu(sua) parceiro(a) não vê este cartão nem as compras dele.</span></span>
                    </label>
                @endif
                @if($editing)
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="archived" value="1" class="checkbox mt-0.5" @checked(old('archived', $c->archived))>
                        <span class="text-sm"><span class="font-medium text-slate-800">Arquivar cartão</span>
                            <span class="block text-xs text-slate-500">Cancelou o cartão? Arquive para manter o histórico.</span></span>
                    </label>
                @endif
            </div>
        @endif

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('cards.index') }}" class="btn-secondary">Cancelar</a>
            <button class="btn-primary">{{ $editing ? 'Salvar alterações' : 'Cadastrar cartão' }}</button>
        </div>
    </form>
</x-layouts.app>
