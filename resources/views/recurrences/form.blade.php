@php
    $r = $recurrence;
    $editing = $r->exists;
    $isCouple = household()->isCouple();
    $kind = old('kind', $r->kind ?? 'fixed');
    $kindTitles = ['salary' => 'salário fixo', 'extra_income' => 'renda extra recorrente', 'fixed' => 'gasto fixo'];
    $pageTitle = ($editing ? 'Editar ' : 'Novo ').($kindTitles[$r->kind] ?? 'item fixo');
    $method = old('method', $r->credit_card_id ? 'card:'.$r->credit_card_id : ($r->account_id ? 'account:'.$r->account_id : ''));
    $catOptions = $categories->map(fn ($c) => ['id' => $c->id, 'name' => trim(($c->icon ?? '').' '.$c->name), 'type' => $c->type])->values();
@endphp
<x-layouts.app :title="ucfirst($pageTitle)">
    <x-page-header :title="ucfirst($pageTitle)" subtitle="Ele vai gerar lançamentos previstos automaticamente todo mês.">
        <x-slot:actions><a href="{{ route('recurrences.index') }}" class="btn-ghost">← Voltar</a></x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('recurrences.update', $r) : route('recurrences.store') }}"
          x-data="{
              kind: @js($kind),
              categoryId: @js((string) old('category_id', $r->category_id ?? '')),
              cats: @js($catOptions),
              get type() { return this.kind === 'fixed' ? 'expense' : 'income' },
              get filtered() { return this.cats.filter(c => c.type === this.type) },
          }"
          x-init="$watch('kind', () => { if (! filtered.some(c => String(c.id) === categoryId)) categoryId = '' })"
          class="card card-pad max-w-2xl space-y-5">
        @csrf
        @if($editing) @method('PUT') @endif

        <div>
            <span class="label">O que é?</span>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                @foreach(\App\Models\Recurrence::KINDS as $k => $label)
                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-medium transition"
                           :class="kind === @js($k) ? 'border-brand-500 bg-brand-50 text-brand-800 ring-2 ring-brand-500/20' : 'border-slate-200 text-slate-600 hover:bg-slate-50'">
                        <input type="radio" name="kind" value="{{ $k }}" x-model="kind" class="sr-only">
                        <span>{{ ['salary' => '💼', 'extra_income' => '✨', 'fixed' => '📌'][$k] }}</span> {{ $label }}
                    </label>
                @endforeach
            </div>
            <x-field-error name="kind" />
        </div>

        <div>
            <label class="label" for="f-description">Descrição</label>
            <input id="f-description" name="description" class="input @error('description') input-error @enderror" required maxlength="120"
                   value="{{ old('description', $r->description) }}"
                   :placeholder="kind === 'salary' ? 'Ex.: Salário empresa X' : (kind === 'extra_income' ? 'Ex.: Aluguel do apê' : 'Ex.: Aluguel, internet, financiamento do carro')">
            <x-field-error name="description" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-money-input name="amount" :value="$r->amount" label="Valor" required />
            <div>
                <label class="label" for="f-frequency">Frequência</label>
                <select id="f-frequency" name="frequency" class="input">
                    @foreach(\App\Models\Recurrence::FREQUENCIES as $k => $label)
                        <option value="{{ $k }}" @selected(old('frequency', $r->frequency) === $k)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-field-error name="frequency" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="label" for="f-day">Dia do mês</label>
                <input id="f-day" type="number" name="day_of_month" min="1" max="31" class="input tabular @error('day_of_month') input-error @enderror"
                       value="{{ old('day_of_month', $r->day_of_month) }}" required>
                <p class="help">Em meses mais curtos, usamos o último dia.</p>
                <x-field-error name="day_of_month" />
            </div>
            <div>
                <label class="label" for="f-start">Começa em</label>
                <input id="f-start" type="date" name="start_date" class="input @error('start_date') input-error @enderror"
                       value="{{ old('start_date', $r->start_date?->format('Y-m-d')) }}" required>
                <x-field-error name="start_date" />
            </div>
            <div>
                <label class="label" for="f-end">Termina em <span class="font-normal text-slate-400">(opcional)</span></label>
                <input id="f-end" type="date" name="end_date" class="input @error('end_date') input-error @enderror"
                       value="{{ old('end_date', $r->end_date?->format('Y-m-d')) }}">
                <x-field-error name="end_date" />
            </div>
        </div>
        <p class="-mt-3 help" x-show="kind === 'fixed'">💡 Tem fim? Ex.: financiamento ou consórcio — informe a data da última parcela e ele some sozinho depois.</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="f-category">Categoria</label>
                <select id="f-category" name="category_id" class="input" x-model="categoryId">
                    <option value="">Sem categoria</option>
                    <template x-for="c in filtered" :key="c.id">
                        <option :value="String(c.id)" x-text="c.name" :selected="String(c.id) === categoryId"></option>
                    </template>
                </select>
                <x-field-error name="category_id" />
            </div>
            <div>
                <label class="label" for="f-method" x-text="kind === 'fixed' ? 'Pago com' : 'Cai em qual conta?'">Conta</label>
                <select id="f-method" name="method" class="input">
                    <option value="">— Escolher depois —</option>
                    @if($accounts->isNotEmpty())
                        <optgroup label="Contas">
                            @foreach($accounts as $a)
                                <option value="account:{{ $a->id }}" @selected($method === 'account:'.$a->id)>🏦 {{ $a->name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    @if($cards->isNotEmpty())
                        <optgroup label="Cartões de crédito" x-show="kind === 'fixed'" :disabled="kind !== 'fixed'">
                            @foreach($cards as $c)
                                <option value="card:{{ $c->id }}" @selected($method === 'card:'.$c->id)>💳 {{ $c->name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
                <x-field-error name="account_id" />
                <x-field-error name="credit_card_id" />
            </div>
        </div>

        @if($isCouple)
            <div>
                <label class="label" for="f-user" x-text="kind === 'fixed' ? 'De quem é este gasto?' : 'De quem é esta renda?'">De quem é?</label>
                <select id="f-user" name="user_id" class="input">
                    @foreach($members as $m)
                        <option value="{{ $m->id }}" @selected((int) old('user_id', $r->user_id ?? auth()->id()) === $m->id)>{{ $m->name }}</option>
                    @endforeach
                </select>
                <x-field-error name="user_id" />
            </div>
        @endif

        <div class="space-y-3 rounded-xl bg-slate-50 p-4">
            @if($isCouple)
                <label class="flex items-start gap-3" x-show="kind === 'fixed'">
                    <input type="checkbox" name="is_shared" value="1" class="checkbox mt-0.5" @checked(old('is_shared', $r->is_shared))>
                    <span class="text-sm"><span class="font-medium text-slate-800">Despesa compartilhada do casal</span>
                        <span class="block text-xs text-slate-500">Entra na divisão de contas entre vocês dois.</span></span>
                </label>
            @endif
            <label class="flex items-start gap-3">
                <input type="checkbox" name="auto_paid" value="1" class="checkbox mt-0.5" @checked(old('auto_paid', $r->auto_paid))>
                <span class="text-sm"><span class="font-medium text-slate-800" x-text="kind === 'fixed' ? 'Marcar como pago automaticamente na data' : 'Marcar como recebido automaticamente na data'">Marcar como pago/recebido automaticamente na data</span>
                    <span class="block text-xs text-slate-500">Ideal para débito automático ou salário que sempre cai certinho.</span></span>
            </label>
            @if($editing)
                <label class="flex items-start gap-3">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" class="checkbox mt-0.5" @checked(old('active', $r->active))>
                    <span class="text-sm"><span class="font-medium text-slate-800">Ativo</span>
                        <span class="block text-xs text-slate-500">Desmarque para pausar sem perder o histórico.</span></span>
                </label>
            @endif
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('recurrences.index') }}" class="btn-secondary">Cancelar</a>
            <button class="btn-primary">{{ $editing ? 'Salvar alterações' : 'Salvar' }}</button>
        </div>
    </form>
</x-layouts.app>
