@php
    $editing = $goal->exists;
    $types = \App\Models\Goal::TYPES;
    $typeIcons = collect($types)->map(fn ($t) => $t[1])->all();
    $emojis = ['🛟', '🌟', '✈️', '🏖️', '🛍️', '🏡', '🚗', '🏍️', '🎓', '💍', '👶', '🐶', '💻', '📱', '🎸', '🌴', '💪', '🎁', '🧳', '🎯'];
    $targetDate = old('target_date', $goal->target_date?->toDateString());
@endphp

<x-layouts.app :title="$editing ? 'Editar meta' : 'Nova meta'">
    <x-page-header :title="$editing ? 'Editar meta' : 'Nova meta'" subtitle="Defina quanto e quando. O Prumo calcula quanto guardar por mês.">
        <x-slot:actions>
            <a href="{{ route('goals.index') }}" class="btn-secondary">← Voltar</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('goals.update', $goal) : route('goals.store') }}"
          class="mx-auto grid max-w-4xl gap-6 @4xl:grid-cols-5"
          x-data="{
              icon: @js(old('icon', $goal->icon ?: '🎯')),
              type: @js(old('type', $goal->type ?: 'dream')),
              typeIcons: @js($typeIcons),
              iconTouched: {{ $editing ? 'true' : 'false' }},
              date: @js((string) $targetDate),
              name: @js((string) old('name', $goal->name)),
              toNum(id) {
                  const el = document.getElementById(id);
                  if (!el || el.value === '') return 0;
                  return Number(el.value.replace(/\./g, '').replace(',', '.')) || 0;
              },
              target: 0, current: 0, capacity: {{ (float) ($capacity ?? 0) }},
              sync() { this.target = this.toNum('f-target_amount'); this.current = this.toNum('f-current_amount'); },
              get months() {
                  if (!this.date) return null;
                  const d = new Date(this.date + 'T00:00:00'), n = new Date();
                  return Math.max(1, (d.getFullYear() - n.getFullYear()) * 12 + (d.getMonth() - n.getMonth()));
              },
              get remaining() { return Math.max(0, this.target - this.current); },
              get monthly() { return this.months ? this.remaining / this.months : null; },
              get pctDone() { return this.target > 0 ? Math.min(100, this.current / this.target * 100) : 0; },
              onType() { if (!this.iconTouched && this.typeIcons[this.type]) this.icon = this.typeIcons[this.type]; },
          }"
          x-init="$nextTick(() => sync())" @input="$nextTick(() => sync())">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card card-pad space-y-5 @4xl:col-span-3">
            <div>
                <label class="label" for="name">Nome da meta</label>
                <input id="name" name="name" x-model="name" value="{{ old('name', $goal->name) }}" class="input @error('name') input-error @enderror"
                       placeholder="Ex.: Viagem para o Nordeste" required maxlength="80">
                <x-field-error name="name" />
            </div>

            <div>
                <label class="label" for="type">Tipo</label>
                <select id="type" name="type" x-model="type" @change="onType()" class="input @error('type') input-error @enderror">
                    @foreach($types as $key => [$label, $ic])
                        <option value="{{ $key }}" @selected(old('type', $goal->type) === $key)>{{ $ic }} {{ $label }}</option>
                    @endforeach
                </select>
                <x-field-error name="type" />
            </div>

            <div>
                <label class="label" for="icon">Ícone</label>
                <div class="flex items-center gap-3">
                    <input id="icon" name="icon" x-model="icon" @input="iconTouched = true" class="input w-20 text-center text-xl" maxlength="16" required>
                    <span class="text-xs text-slate-500">Digite um emoji ou escolha abaixo</span>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach($emojis as $e)
                        <button type="button" @click="icon = '{{ $e }}'; iconTouched = true"
                                class="grid h-9 w-9 place-items-center rounded-xl text-lg transition hover:bg-brand-50"
                                :class="icon === '{{ $e }}' ? 'bg-brand-100 ring-2 ring-brand-500' : 'bg-slate-50'">{{ $e }}</button>
                    @endforeach
                </div>
                <x-field-error name="icon" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-money-input name="target_amount" :value="$goal->target_amount" label="Valor da meta" required />
                <x-money-input name="current_amount" :value="$goal->current_amount" label="Já tenho guardado" help="Se estiver começando agora, deixe em branco." />
            </div>

            <div>
                <label class="label" for="target_date">Quando quer alcançar?</label>
                <input id="target_date" type="date" name="target_date" x-model="date" value="{{ $targetDate }}"
                       class="input @error('target_date') input-error @enderror">
                <p class="help">Opcional, mas com data fica muito mais fácil manter o foco.</p>
                <x-field-error name="target_date" />
            </div>

            @if(household()->isCouple())
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3.5 hover:bg-slate-50">
                    <input type="checkbox" name="is_shared" value="1" class="checkbox mt-0.5" @checked(old('is_shared', $goal->is_shared))>
                    <span>
                        <span class="block text-sm font-semibold text-slate-800">Meta compartilhada do casal</span>
                        <span class="block text-xs text-slate-500">Os dois veem e podem aportar.</span>
                    </span>
                </label>
                    <label class="mt-3 flex items-start gap-3">
                        <input type="checkbox" name="is_private" value="1" class="checkbox mt-0.5" @checked(old('is_private', $goal->is_private))>
                        <span class="text-sm"><span class="font-medium text-slate-800">🔒 Meta privada</span>
                            <span class="block text-xs text-slate-500">Só você vê esta meta.</span></span>
                    </label>
            @endif
        </div>

        {{-- Prévia --}}
        <div class="@4xl:col-span-2">
            <div class="sticky top-24 space-y-4">
                <div class="overflow-hidden rounded-3xl hero p-5 text-white shadow-lg">
                    <div class="flex items-center gap-3">
                        <div class="grid h-12 w-12 place-items-center rounded-2xl bg-white/15 text-2xl" x-text="icon || '🎯'"></div>
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-brand-200">Prévia</div>
                            <div class="truncate font-display text-lg font-bold" x-text="name || 'Sua meta'">Sua meta</div>
                        </div>
                    </div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/15">
                        <div class="h-full rounded-full bg-emerald-400 transition-all" :style="`width: ${pctDone}%`"></div>
                    </div>
                    <div class="mt-1.5 flex justify-between text-xs text-brand-100">
                        <span class="tabular" x-text="brl(current)"></span>
                        <span class="tabular" x-text="'de ' + brl(target)"></span>
                    </div>

                    <div class="mt-5 rounded-2xl bg-white/10 p-4">
                        <template x-if="monthly !== null && remaining > 0">
                            <div>
                                <div class="text-xs text-brand-200">Guardar por mês</div>
                                <div class="font-display text-3xl font-bold tabular" x-text="brl(monthly)"></div>
                                <div class="mt-1 text-xs text-brand-100">durante <span x-text="months"></span> <span x-text="months === 1 ? 'mês' : 'meses'"></span> para juntar <span class="tabular" x-text="brl(remaining)"></span></div>
                            </div>
                        </template>
                        <template x-if="remaining > 0">
                            <div class="mt-3 border-t border-white/15 pt-3 text-xs">
                                <template x-if="capacity <= 0"><span class="text-amber-200">⚠️ Hoje sua renda média não sobra no fim do mês. Reduza gastos ou aumente a renda para viabilizar metas.</span></template>
                                <template x-if="capacity > 0 && monthly !== null && monthly <= capacity"><span class="text-emerald-200">✓ Cabe na sua capacidade de poupança (<span x-text="brl(capacity)"></span>/mês).</span></template>
                                <template x-if="capacity > 0 && monthly !== null && monthly > capacity"><span class="text-amber-200">⚠️ Acima do que sobra por mês (<span x-text="brl(capacity)"></span>). No seu ritmo atual levaria <span x-text="Math.ceil(remaining / capacity)"></span> meses.</span></template>
                                <template x-if="capacity > 0 && monthly === null"><span class="text-brand-100">No ritmo da sua sobra atual (<span x-text="brl(capacity)"></span>/mês): <span x-text="Math.ceil(remaining / capacity)"></span> meses.</span></template>
                            </div>
                        </template>
                        <template x-if="target > 0 && remaining <= 0">
                            <div class="text-sm font-semibold">🏆 Você já tem o valor da meta!</div>
                        </template>
                        <template x-if="monthly === null && remaining > 0">
                            <div class="text-sm text-brand-100">Defina uma data para ver quanto guardar por mês. Faltam <strong class="tabular text-white" x-text="brl(remaining)"></strong>.</div>
                        </template>
                        <template x-if="target <= 0">
                            <div class="text-sm text-brand-100">Informe o valor da meta para ver a simulação.</div>
                        </template>
                    </div>
                </div>
                <p class="px-1 text-xs text-slate-500">💡 Dica: agende o aporte para o dia do salário. Guardar primeiro e gastar depois é o segredo de quem chega lá.</p>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end @4xl:col-span-5">
            <a href="{{ route('goals.index') }}" class="btn-secondary">Cancelar</a>
            <button class="btn-primary">{{ $editing ? 'Salvar alterações' : 'Criar meta' }}</button>
        </div>
    </form>
</x-layouts.app>
