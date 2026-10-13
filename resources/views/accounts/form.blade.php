@php
    $a = $account;
    $editing = $a->exists;
    $swatches = ['#0f766e', '#059669', '#2563eb', '#0891b2', '#7c3aed', '#db2777', '#dc2626', '#ea580c', '#f59e0b', '#475569', '#820ad1', '#ec7000'];
@endphp
<x-layouts.app :title="$editing ? 'Editar conta' : 'Nova conta'">
    <x-page-header :title="$editing ? 'Editar conta' : 'Nova conta'" subtitle="Conta corrente, poupança, carteira ou vale — cadastre onde seu dinheiro está.">
        <x-slot:actions><a href="{{ route('accounts.index') }}" class="btn-ghost">← Voltar</a></x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('accounts.update', $a) : route('accounts.store') }}" class="card card-pad max-w-2xl space-y-5"
          x-data="{ color: @js(old('color', $a->color ?? '#0f766e')), name: @js(old('name', $a->name ?? '')) }">
        @csrf
        @if($editing) @method('PUT') @endif

        {{-- Prévia --}}
        <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3">
            <span class="grid h-10 w-10 place-items-center rounded-xl text-sm font-bold text-white" :style="`background-color: ${color}`"
                  x-text="(name || '?').slice(0, 2).toUpperCase()"></span>
            <div class="text-sm"><div class="font-semibold text-slate-800" x-text="name || 'Nome da conta'"></div><div class="text-xs text-slate-500">Prévia</div></div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="f-name">Nome</label>
                <input id="f-name" name="name" x-model="name" class="input @error('name') input-error @enderror" required maxlength="80" placeholder="Ex.: Nubank, Carteira, VR">
                <x-field-error name="name" />
            </div>
            <div>
                <label class="label" for="f-type">Tipo</label>
                <select id="f-type" name="type" class="input">
                    @foreach(\App\Models\Account::TYPES as $k => $label)
                        <option value="{{ $k }}" @selected(old('type', $a->type) === $k)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-field-error name="type" />
            </div>
        </div>

        {{-- Banco: combobox pesquisável --}}
        <div x-data="{
                banks: @js($banks),
                code: @js(old('bank_code', $a->bank_code ?? '')),
                bankName: @js(old('bank_name', $a->bank_name ?? '')),
                q: '', open: false, hi: 0,
                init() { this.q = this.bankName ? (this.code ? this.code + ' · ' : '') + this.bankName : '' },
                get results() {
                    const t = this.q.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
                    if (! t || (this.bankName && this.q === this.label())) return this.banks.slice(0, 50);
                    return this.banks.filter(b => (b.code + ' ' + b.name).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').includes(t)).slice(0, 50);
                },
                label() { return this.bankName ? (this.code ? this.code + ' · ' : '') + this.bankName : '' },
                pick(b) { this.code = b.code; this.bankName = b.name; this.q = this.label(); this.open = false },
                clear() { this.code = ''; this.bankName = ''; this.q = ''; },
             }" @click.outside="open = false" class="relative">
            <label class="label" for="f-bank">Banco <span class="font-normal text-slate-400">(opcional)</span></label>
            <input type="hidden" name="bank_code" :value="code">
            <input type="hidden" name="bank_name" :value="bankName">
            <div class="relative">
                <input id="f-bank" x-ref="bankInput" type="text" class="input pr-9" x-model="q" autocomplete="off" placeholder="Digite o nome ou código do banco..."
                       @focus="open = true; hi = 0" @input="open = true; hi = 0; if (q === '') clear()"
                       @keydown.arrow-down.prevent="open = true; hi = Math.min(hi + 1, results.length - 1)"
                       @keydown.arrow-up.prevent="hi = Math.max(hi - 1, 0)"
                       @keydown.enter.prevent="if (open && results[hi]) pick(results[hi])"
                       @keydown.escape="open = false">
                <button type="button" x-show="q" @click="clear(); $refs.bankInput.focus()" class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-600" aria-label="Limpar">✕</button>
            </div>
            <ul x-cloak x-show="open && results.length" class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                <template x-for="(b, i) in results" :key="b.code">
                    <li>
                        <button type="button" @click="pick(b)" @mouseenter="hi = i"
                                :class="i === hi ? 'bg-brand-50 text-brand-800' : 'text-slate-700'"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm">
                            <span class="w-10 shrink-0 text-xs tabular text-slate-400" x-text="b.code"></span>
                            <span class="truncate" x-text="b.name"></span>
                        </button>
                    </li>
                </template>
            </ul>
            <p x-cloak x-show="open && q && ! results.length" class="help">Nenhum banco encontrado. Tudo bem — o banco é opcional.</p>
            <x-field-error name="bank_code" />
            <x-field-error name="bank_name" />
        </div>

        <x-money-input name="initial_balance" :value="$a->initial_balance ?? 0" label="Saldo inicial"
                       help="Saldo no dia em que começou a usar o Prumo. Pode ser negativo se a conta estiver no cheque especial." />

        <div>
            <span class="label">Cor</span>
            <div class="flex flex-wrap items-center gap-2">
                @foreach($swatches as $sw)
                    <button type="button" @click="color = '{{ $sw }}'" class="h-8 w-8 rounded-full ring-offset-2 transition"
                            :class="color.toLowerCase() === '{{ $sw }}' ? 'ring-2 ring-slate-800' : 'hover:scale-110'"
                            style="background-color: {{ $sw }}" aria-label="Cor {{ $sw }}"></button>
                @endforeach
                <label class="relative ml-1 inline-flex h-8 cursor-pointer items-center gap-1 rounded-full border border-slate-200 px-2 text-xs text-slate-600 hover:bg-slate-50">
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
                        <input type="checkbox" name="is_shared" value="1" class="checkbox mt-0.5" @checked(old('is_shared', $a->is_shared))>
                        <span class="text-sm"><span class="font-medium text-slate-800">Conta compartilhada do casal</span>
                            <span class="block text-xs text-slate-500">Conta conjunta ou usada pelos dois.</span></span>
                    </label>
                    <label class="mt-3 flex items-start gap-3">
                        <input type="checkbox" name="is_private" value="1" class="checkbox mt-0.5" @checked(old('is_private', $a->is_private))>
                        <span class="text-sm"><span class="font-medium text-slate-800">🔒 Conta privada</span>
                            <span class="block text-xs text-slate-500">Seu(sua) parceiro(a) não vê esta conta nem os lançamentos dela.</span></span>
                    </label>
                @endif
                @if($editing)
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="archived" value="1" class="checkbox mt-0.5" @checked(old('archived', $a->archived))>
                        <span class="text-sm"><span class="font-medium text-slate-800">Arquivar conta</span>
                            <span class="block text-xs text-slate-500">Some das listas e do saldo total, mas o histórico continua guardado.</span></span>
                    </label>
                @endif
            </div>
        @endif

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('accounts.index') }}" class="btn-secondary">Cancelar</a>
            <button class="btn-primary">{{ $editing ? 'Salvar alterações' : 'Criar conta' }}</button>
        </div>
    </form>
</x-layouts.app>
