@php
    $editing = $debt->exists;
    $types = \App\Models\Debt::TYPES;
    $rateValue = old('monthly_interest_rate', $editing && $debt->monthly_interest_rate !== null ? number_format((float) $debt->monthly_interest_rate, 2, ',', '') : '');
    $typeRates = collect($types)->map(fn ($t) => $t[1])->all();
@endphp

<x-layouts.app :title="$editing ? 'Editar dívida' : 'Nova dívida'">
    <x-page-header :title="$editing ? 'Editar dívida' : 'Cadastrar dívida'"
                   subtitle="Quanto mais real a informação, mais certeiro fica o seu plano de saída. Valores aproximados já ajudam.">
        <x-slot:actions>
            <a href="{{ route('debts.index') }}" class="btn-secondary">← Voltar</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('debts.update', $debt) : route('debts.store') }}" class="mx-auto max-w-3xl space-y-6"
          x-data="{
              type: @js(old('type', $debt->type ?? 'personal_loan')),
              rate: @js((string) $rateValue),
              rates: @js($typeRates),
              get monthly() {
                  const v = this.rate === '' ? this.rates[this.type] ?? 3 : Number(String(this.rate).replace(/\./g, '').replace(',', '.'));
                  return isNaN(v) ? null : v;
              },
              get annual() {
                  return this.monthly === null ? null : (Math.pow(1 + this.monthly / 100, 12) - 1) * 100;
              },
              fmt(v, d = 1) { return Number(v).toLocaleString('pt-BR', { minimumFractionDigits: d, maximumFractionDigits: d }); },
          }">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card card-pad space-y-5">
            <h3 class="card-title">Sobre a dívida</h3>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="creditor">Com quem é a dívida?</label>
                    <input id="creditor" name="creditor" value="{{ old('creditor', $debt->creditor) }}" class="input @error('creditor') input-error @enderror"
                           placeholder="Ex.: Nubank, Banco do Brasil, tia Maria" required maxlength="120">
                    <x-field-error name="creditor" />
                </div>
                <div>
                    <label class="label" for="type">Tipo</label>
                    <select id="type" name="type" x-model="type" class="input @error('type') input-error @enderror">
                        @foreach($types as $key => [$label, $rate])
                            <option value="{{ $key }}" @selected(old('type', $debt->type) === $key)>{{ $label }} (média {{ num($rate, 1) }}% a.m.)</option>
                        @endforeach
                    </select>
                    <x-field-error name="type" />
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-money-input name="current_balance" :value="$debt->current_balance" label="Quanto deve hoje (saldo devedor)" required
                               help="Se não souber exato, use o valor da última fatura ou extrato." />
                <x-money-input name="original_amount" :value="$debt->original_amount" label="Valor original (opcional)"
                               help="Usado para mostrar quanto você já quitou." />
            </div>
        </div>

        <div class="card card-pad space-y-5">
            <h3 class="card-title">Juros e parcelas</h3>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="monthly_interest_rate">Juros ao mês</label>
                    <div class="relative">
                        <input id="monthly_interest_rate" name="monthly_interest_rate" x-model="rate" inputmode="decimal"
                               class="input pr-20 tabular @error('monthly_interest_rate') input-error @enderror"
                               :placeholder="fmt(rates[type] ?? 3, 1)">
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-sm text-slate-400">% ao mês</span>
                    </div>
                    <p class="help">Não sabe? Deixe em branco e usamos a média do tipo.</p>
                    <p class="mt-1 text-xs" x-show="annual !== null" x-cloak>
                        <span :class="monthly > 5 ? 'text-rose-600 font-semibold' : 'text-slate-500'">
                            ≈ <span class="tabular" x-text="fmt(annual)"></span>% ao ano
                            <span x-show="rate === ''">(média do tipo)</span>
                        </span>
                        <span x-show="monthly > 5" class="text-rose-600"> — juros muito altos, priorize esta 🔥</span>
                    </p>
                    <x-field-error name="monthly_interest_rate" />
                </div>
                <x-money-input name="minimum_payment" :value="$debt->minimum_payment" label="Parcela / pagamento mínimo"
                               help="Quanto é cobrado por mês. Deixe em branco se não houver." />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="due_day">Dia do vencimento</label>
                    <input id="due_day" type="number" min="1" max="31" name="due_day" value="{{ old('due_day', $debt->due_day) }}"
                           class="input tabular @error('due_day') input-error @enderror" placeholder="Ex.: 10">
                    <x-field-error name="due_day" />
                </div>
                @if($editing)
                    <div>
                        <label class="label" for="status">Situação</label>
                        <select id="status" name="status" class="input">
                            @foreach(['active' => 'Ativa', 'negotiating' => 'Em negociação', 'paid' => 'Quitada 🎉'] as $k => $l)
                                <option value="{{ $k }}" @selected(old('status', $debt->status) === $k)>{{ $l }}</option>
                            @endforeach
                        </select>
                        <x-field-error name="status" />
                    </div>
                @endif
            </div>

            <div class="space-y-3">
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3.5 hover:bg-slate-50">
                    <input type="checkbox" name="is_overdue" value="1" class="checkbox mt-0.5" @checked(old('is_overdue', $debt->is_overdue))>
                    <span>
                        <span class="block text-sm font-semibold text-slate-800">Está em atraso / nome negativado</span>
                        <span class="block text-xs text-slate-500">Dívidas atrasadas recebem prioridade no plano e costumam ter bons descontos na negociação.</span>
                    </span>
                </label>
                @if(household()->isCouple())
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3.5 hover:bg-slate-50">
                        <input type="checkbox" name="is_shared" value="1" class="checkbox mt-0.5" @checked(old('is_shared', $debt->is_shared))>
                        <span>
                            <span class="block text-sm font-semibold text-slate-800">Dívida compartilhada do casal</span>
                            <span class="block text-xs text-slate-500">Aparece para os dois e entra no plano conjunto.</span>
                        </span>
                    </label>
                    <label class="mt-3 flex items-start gap-3">
                        <input type="checkbox" name="is_private" value="1" class="checkbox mt-0.5" @checked(old('is_private', $debt->is_private))>
                        <span class="text-sm"><span class="font-medium text-slate-800">🔒 Dívida privada</span>
                            <span class="block text-xs text-slate-500">Só você vê esta dívida.</span></span>
                    </label>
                @endif
            </div>

            <div>
                <label class="label" for="notes">Observações</label>
                <textarea id="notes" name="notes" rows="3" class="input" maxlength="2000"
                          placeholder="Ex.: número do contrato, proposta recebida, contato do atendente...">{{ old('notes', $debt->notes) }}</textarea>
                <x-field-error name="notes" />
            </div>
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <a href="{{ route('debts.index') }}" class="btn-secondary">Cancelar</a>
            <button class="btn-primary">{{ $editing ? 'Salvar alterações' : 'Cadastrar dívida' }}</button>
        </div>
    </form>
</x-layouts.app>
