@php
    $t = $transaction;
    $editing = $t->exists;
    $method = old('method', $t->credit_card_id ? 'card:'.$t->credit_card_id : ($t->account_id ? 'account:'.$t->account_id : ($accounts->first() ? 'account:'.$accounts->first()->id : '')));
    $catList = $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->icon.' '.$c->name, 'type' => $c->type])->values();
@endphp
<x-layouts.app :title="$editing ? 'Editar lançamento' : 'Novo lançamento'">
    <div class="mx-auto max-w-2xl"
         x-data="{
            type: @js(old('type', $t->type)),
            kind: @js(old('kind', $t->kind)),
            method: @js($method),
            installments: @js((int) old('installments', 2)),
            mode: @js(old('installment_value_mode', 'total')),
            hourly: {{ (float) $hourly }},
            cats: @js($catList),
            category: @js((string) old('category_id', $t->category_id)),
            setType(v) {
                this.type = v;
                this.kind = v === 'income' ? 'extra_income' : (v === 'transfer' ? 'transfer' : 'daily');
                if (!this.cats.find(c => String(c.id) === this.category && c.type === v)) this.category = '';
            },
            get isCard() { return this.method.startsWith('card:') },
         }">
        <x-page-header :title="$editing ? 'Editar lançamento' : 'Novo lançamento'" :subtitle="$editing && $t->installment_group ? 'Parcela '.$t->installment_number.' de '.$t->installment_total.' — alterações valem só para esta parcela.' : null" />

        <form method="POST" action="{{ $editing ? route('transactions.update', $t) : route('transactions.store') }}" class="card card-pad space-y-5">
            @csrf
            @if($editing) @method('PUT') @endif
            <input type="hidden" name="type" :value="type">

            @unless($editing)
                <div class="grid grid-cols-3 gap-2 rounded-2xl bg-slate-100 p-1">
                    @foreach(['expense' => ['Despesa', 'text-rose-600'], 'income' => ['Receita', 'text-emerald-600'], 'transfer' => ['Transferência', 'text-slate-700']] as $v => [$l, $c])
                        <button type="button" @click="setType('{{ $v }}')" class="rounded-xl py-2.5 text-sm font-semibold transition" :class="type === '{{ $v }}' ? 'bg-white shadow-sm {{ $c }}' : 'text-slate-500'">{{ $l }}</button>
                    @endforeach
                </div>
            @endunless

            {{-- Natureza --}}
            <div x-show="type !== 'transfer'">
                <label class="label">Que tipo de lançamento?</label>
                <div class="flex flex-wrap gap-2">
                    <template x-if="type === 'expense'">
                        <div class="contents">
                            @foreach(['daily' => '🛒 Dia a dia', 'installment' => '🧾 Parcelado', 'fixed' => '📌 Conta fixa (avulsa)', 'debt_payment' => '⛓️ Pagamento de dívida', 'goal' => '🎯 Aporte em meta'] as $k => $l)
                                @if(! $editing || $k !== 'installment' || $t->kind === 'installment')
                                    <label class="cursor-pointer rounded-full border px-3 py-1.5 text-sm transition" :class="kind === '{{ $k }}' ? 'border-brand-600 bg-brand-50 font-semibold text-brand-800' : 'border-slate-200 text-slate-600 hover:border-slate-300'">
                                        <input type="radio" name="kind" value="{{ $k }}" x-model="kind" class="sr-only">{{ $l }}
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    </template>
                    <template x-if="type === 'income'">
                        <div class="contents">
                            @foreach(['salary' => '💼 Salário', 'extra_income' => '✨ Renda extra', 'other_income' => '➕ Outra receita'] as $k => $l)
                                <label class="cursor-pointer rounded-full border px-3 py-1.5 text-sm transition" :class="kind === '{{ $k }}' ? 'border-brand-600 bg-brand-50 font-semibold text-brand-800' : 'border-slate-200 text-slate-600 hover:border-slate-300'">
                                    <input type="radio" name="kind" value="{{ $k }}" x-model="kind" class="sr-only">{{ $l }}
                                </label>
                            @endforeach
                        </div>
                    </template>
                </div>
                <p x-show="kind === 'fixed'" x-cloak class="help">Conta que se repete todo mês? Cadastre em <a href="{{ route('recurrences.create', ['tipo' => 'fixed']) }}" class="font-semibold text-brand-700 underline">Renda & fixos</a> para o Prumo lançar sozinho.</p>
                <p x-show="kind === 'salary'" x-cloak class="help">Salário fixo mensal? Cadastre em <a href="{{ route('recurrences.create', ['tipo' => 'salary']) }}" class="font-semibold text-brand-700 underline">Renda & fixos</a> para entrar nas projeções.</p>
            </div>
            <input type="hidden" name="kind" value="transfer" x-bind:disabled="type !== 'transfer'">

            <div class="grid gap-4 sm:grid-cols-2">
                <div x-data="money(@js(old('amount', $t->amount)))" class="sm:col-span-1">
                    <label class="label" for="amount"><span x-show="kind !== 'installment'">Valor</span><span x-show="kind === 'installment'" x-text="mode === 'total' ? 'Valor total da compra' : 'Valor de cada parcela'"></span></label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-medium text-slate-400">R$</span>
                        <input id="amount" name="amount" x-model="raw" @input="format($event)" inputmode="numeric" placeholder="0,00" required autofocus class="input pl-10 text-lg font-bold tabular @error('amount') input-error @enderror">
                    </div>
                    <p x-show="type === 'expense' && hourly > 0 && value > 0" x-cloak class="help">⏱️ Isso equivale a <b x-text="(value / hourly).toLocaleString('pt-BR', {maximumFractionDigits: 1})"></b> horas do seu trabalho.</p>
                    <x-field-error name="amount" />
                </div>
                <div>
                    <label class="label" for="date">Data</label>
                    <input id="date" type="date" name="date" value="{{ old('date', $t->date?->toDateString()) }}" required class="input">
                    <div class="mt-1.5 flex gap-1.5">
                        <button type="button" class="badge-slate hover:bg-slate-200" onclick="document.getElementById('date').value = '{{ now()->toDateString() }}'">hoje</button>
                        <button type="button" class="badge-slate hover:bg-slate-200" onclick="document.getElementById('date').value = '{{ now()->subDay()->toDateString() }}'">ontem</button>
                    </div>
                </div>
            </div>

            <div>
                <label class="label" for="description">Descrição</label>
                <input id="description" name="description" value="{{ old('description', $t->description) }}" required maxlength="190" class="input @error('description') input-error @enderror" placeholder="Ex.: Mercado, Uber, Freela site, Geladeira...">
                <p class="help" x-show="!category" x-cloak>Sem categoria? O Prumo sugere automaticamente pela descrição e aprende com suas correções. 🧠</p>
            </div>

            {{-- Parcelamento --}}
            @unless($editing)
                <div x-show="kind === 'installment'" x-cloak class="grid gap-4 rounded-2xl bg-violet-50/60 p-4 ring-1 ring-violet-100 sm:grid-cols-2">
                    <div>
                        <label class="label">Número de parcelas</label>
                        <input type="number" name="installments" x-model.number="installments" min="2" max="120" class="input" :disabled="kind !== 'installment'">
                    </div>
                    <div>
                        <label class="label">O valor informado é</label>
                        <select name="installment_value_mode" x-model="mode" class="input"><option value="total">o total da compra</option><option value="each">o valor de cada parcela</option></select>
                    </div>
                    <p class="text-xs text-violet-800 sm:col-span-2">As parcelas serão lançadas mês a mês<span x-show="isCard"> nas próximas faturas do cartão</span>. Você verá quando terminam no <b>Radar de parcelas</b>.</p>
                </div>
            @endunless

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="method"><span x-text="type === 'transfer' ? 'Conta de origem' : (type === 'income' ? 'Recebido em' : 'Pago com')"></span></label>
                    <select id="method" name="method" x-model="method" class="input">
                        <option value="">— sem conta —</option>
                        <optgroup label="Contas">@foreach($accounts as $a)<option value="account:{{ $a->id }}">{{ $a->name }}</option>@endforeach</optgroup>
                        @if($cards->count())
                            <optgroup label="Cartões de crédito" x-bind:disabled="type !== 'expense' && type !== 'income'">@foreach($cards as $c)<option value="card:{{ $c->id }}">💳 {{ $c->name }}</option>@endforeach</optgroup>
                        @endif
                    </select>
                    <p class="help" x-show="isCard && type === 'income'" x-cloak>Receita no cartão = estorno/crédito na fatura.</p>
                </div>
                <div x-show="type === 'transfer'" x-cloak>
                    <label class="label">Conta de destino</label>
                    <select name="transfer_account_id" class="input" x-bind:disabled="type !== 'transfer'">
                        <option value="">Selecione</option>
                        @foreach($accounts as $a)<option value="{{ $a->id }}" @selected(old('transfer_account_id', $t->transfer_account_id) == $a->id)>{{ $a->name }}</option>@endforeach
                    </select>
                </div>
                <div x-show="type !== 'transfer'">
                    <label class="label" for="category">Categoria</label>
                    <select id="category" name="category_id" x-model="category" class="input">
                        <option value="">✨ Sugerir automaticamente</option>
                        <template x-for="c in cats.filter(c => c.type === type)" :key="c.id"><option :value="String(c.id)" x-text="c.name" :selected="String(c.id) === category"></option></template>
                    </select>
                </div>
            </div>

            <div x-show="kind === 'debt_payment'" x-cloak>
                <label class="label">Qual dívida?</label>
                <select name="debt_id" class="input" x-bind:disabled="kind !== 'debt_payment'">
                    <option value="">Selecione</option>
                    @foreach($debts as $d)<option value="{{ $d->id }}" @selected(old('debt_id', $t->debt_id) == $d->id)>{{ $d->creditor }} — saldo {{ brl($d->current_balance) }}</option>@endforeach
                </select>
                <p class="help">O saldo da dívida é abatido automaticamente.</p>
            </div>
            <div x-show="kind === 'goal'" x-cloak>
                <label class="label">Qual meta?</label>
                <select name="goal_id" class="input" x-bind:disabled="kind !== 'goal'">
                    <option value="">Selecione</option>
                    @foreach($goals as $g)<option value="{{ $g->id }}" @selected(old('goal_id', $t->goal_id) == $g->id)>{{ $g->icon }} {{ $g->name }} — faltam {{ brl($g->remaining()) }}</option>@endforeach
                </select>
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-3 border-t border-slate-100 pt-4">
                <label class="flex items-center gap-2 text-sm text-slate-700" x-show="!isCard && type !== 'transfer'">
                    <input type="hidden" name="paid" value="0">
                    <input type="checkbox" name="paid" value="1" class="checkbox" @checked(old('paid', $t->paid))>
                    <span x-text="type === 'income' ? 'Já recebi' : 'Já paguei'"></span>
                </label>
                @if(household()->isCouple())
                    <label class="flex items-center gap-2 text-sm text-slate-700" x-show="type === 'expense'">
                        <input type="checkbox" name="is_shared" value="1" class="checkbox" @checked(old('is_shared', $t->is_shared))>
                        💑 Despesa compartilhada do casal
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700" title="Seu(sua) parceiro(a) não verá este lançamento">
                        <input type="checkbox" name="is_private" value="1" class="checkbox" @checked(old('is_private', $t->is_private))>
                        🔒 Privado (só eu vejo)
                    </label>
                @endif
            </div>

            <div x-data="{ open: {{ old('notes', $t->notes) ? 'true' : 'false' }} }">
                <button type="button" class="text-xs font-semibold text-slate-500 hover:underline" @click="open = !open" x-show="!open">+ Adicionar observação</button>
                <textarea x-show="open" x-cloak name="notes" rows="2" class="input" placeholder="Observações">{{ old('notes', $t->notes) }}</textarea>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ url()->previous() === url()->current() ? route('transactions.index') : url()->previous() }}" class="btn-ghost">Cancelar</a>
                <div class="flex flex-col gap-2 sm:flex-row">
                    @unless($editing)<button name="another" value="1" class="btn-secondary">Salvar e lançar outro</button>@endunless
                    <button class="btn-primary">Salvar</button>
                </div>
            </div>
        </form>

        @if($editing && $t->installment_group)
            <form method="POST" action="{{ route('transactions.destroy', $t) }}" class="mt-4 text-right" onsubmit="return confirm('Excluir TODAS as parcelas desta compra?')">
                @csrf @method('DELETE')<input type="hidden" name="all" value="1">
                <button class="text-sm font-semibold text-rose-600 hover:underline">Excluir todas as {{ $t->installment_total }} parcelas</button>
            </form>
        @endif
    </div>
</x-layouts.app>
