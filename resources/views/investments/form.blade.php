@php
    $classes = \App\Models\Asset::CLASSES;
    $indexers = \App\Models\Asset::INDEXERS;
    $pricingMap = collect($classes)->map(fn ($c) => $c[2]);
    $editing = $asset->exists;
    $rateValue = old('rate', $asset->rate !== null ? str_replace('.', ',', (string) round($asset->rate, 4)) : '');
    $lastPrice = $asset->pricing() === 'manual' ? $asset->last_price : null;
@endphp

<x-layouts.app :title="$editing ? 'Editar ativo' : 'Novo ativo'">
    <x-page-header :title="$editing ? 'Editar '.$asset->name : 'Novo ativo'"
                   :subtitle="$editing ? 'Ajuste os dados do investimento.' : 'Cadastre um investimento e acompanhe sua evolução de perto.'">
        <x-slot:actions>
            <a href="{{ $editing ? route('investments.show', $asset) : route('investments.index') }}" class="btn-secondary">Voltar</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('investments.update', $asset) : route('investments.store') }}"
          class="mx-auto max-w-2xl space-y-4"
          x-data="{
              cls: @js(old('class', $asset->class ?? 'stock')),
              pricingMap: @js($pricingMap),
              indexer: @js(old('indexer', $asset->indexer ?? 'cdi')),
              ticker: @js(old('ticker', $asset->ticker ?? '')),
              suggestions: [],
              showSug: false,
              loading: false,
              timer: null,
              get pricing() { return this.pricingMap[this.cls] || 'manual' },
              get isMarket() { return ['variable', 'crypto'].includes(this.pricing) },
              get isFixed() { return this.pricing === 'fixed' },
              get isManual() { return this.pricing === 'manual' },
              get rateLabel() {
                  if (this.indexer === 'cdi') return '% do CDI';
                  if (this.indexer === 'ipca' || this.indexer === 'selic') return 'Taxa adicional % a.a.';
                  return 'Taxa % a.a.';
              },
              get ratePlaceholder() {
                  return { cdi: 'Ex.: 110', ipca: 'Ex.: 6', selic: 'Ex.: 0,1', prefixed: 'Ex.: 12' }[this.indexer] || '';
              },
              search() {
                  clearTimeout(this.timer);
                  const q = this.ticker.trim();
                  if (q.length < 2 || this.cls === 'crypto') { this.suggestions = []; this.showSug = false; return; }
                  this.timer = setTimeout(async () => {
                      this.loading = true;
                      try {
                          const res = await fetch(@js(route('investments.tickers')) + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                          this.suggestions = res.ok ? (await res.json()).slice(0, 8) : [];
                          this.showSug = this.suggestions.length > 0;
                      } catch (e) { this.suggestions = []; }
                      this.loading = false;
                  }, 300);
              },
              pick(t) { this.ticker = t; this.showSug = false; },
          }">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card card-pad space-y-5">
            <div>
                <label class="label" for="f-class">Tipo de investimento</label>
                <select id="f-class" name="class" class="input @error('class') input-error @enderror" x-model="cls">
                    @foreach($classes as $key => [$label])
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-field-error name="class" />
            </div>

            {{-- Ticker (renda variável / cripto) --}}
            <div x-show="isMarket" x-cloak class="relative">
                <label class="label" for="f-ticker" x-text="cls === 'crypto' ? 'Símbolo da moeda' : 'Código de negociação (ticker)'">Ticker</label>
                <input id="f-ticker" type="text" name="ticker" autocomplete="off" class="input font-mono uppercase @error('ticker') input-error @enderror"
                       x-model="ticker" @input="search()" @focus="showSug = suggestions.length > 0" @click.outside="showSug = false"
                       @keydown.escape="showSug = false" :disabled="!isMarket"
                       :placeholder="cls === 'crypto' ? 'BTC' : 'PETR4, MXRF11, BOVA11...'">
                <span x-show="loading" class="absolute right-3 top-9 text-xs text-slate-400">buscando…</span>
                <div x-show="showSug" x-cloak x-transition.opacity
                     class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                    <template x-for="t in suggestions" :key="t">
                        <button type="button" @click="pick(t)" class="block w-full rounded-lg px-3 py-2 text-left font-mono text-sm hover:bg-brand-50" x-text="t"></button>
                    </template>
                </div>
                <p class="help" x-show="cls === 'crypto'">Use o símbolo da moeda, como BTC, ETH, SOL. A cotação vem em reais.</p>
                <p class="help" x-show="cls !== 'crypto'">Com o ticker, o Prumo busca a cotação automaticamente.</p>
                <x-field-error name="ticker" />
            </div>

            <div>
                <label class="label" for="f-name">Nome</label>
                <input id="f-name" type="text" name="name" required maxlength="120" class="input @error('name') input-error @enderror"
                       value="{{ old('name', $asset->name) }}"
                       :placeholder="isMarket ? 'Ex.: Petrobras, Bitcoin' : (isFixed ? 'Ex.: CDB Banco X 2028, Tesouro IPCA+ 2035' : 'Ex.: Fundo multimercado, Previdência')">
                <x-field-error name="name" />
            </div>

            {{-- Renda fixa --}}
            <template x-if="isFixed">
                <div class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2" x-show="cls !== 'savings'">
                        <div>
                            <label class="label" for="f-indexer">Indexador</label>
                            <select id="f-indexer" name="indexer" class="input @error('indexer') input-error @enderror" x-model="indexer">
                                @foreach($indexers as $key => $label)
                                    @continue($key === 'savings')
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-field-error name="indexer" />
                        </div>
                        <div>
                            <label class="label" for="f-rate" x-text="rateLabel">Taxa</label>
                            <input id="f-rate" type="text" inputmode="decimal" name="rate" class="input tabular @error('rate') input-error @enderror"
                                   value="{{ $rateValue }}" :placeholder="ratePlaceholder">
                            <x-field-error name="rate" />
                        </div>
                    </div>
                    <p class="help -mt-2" x-show="cls === 'savings'">A poupança segue a regra oficial: 70% da Selic + TR quando a Selic está até 8,5%, ou 0,5% ao mês + TR.</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div x-show="cls !== 'savings'">
                            <label class="label" for="f-maturity">Vencimento</label>
                            <input id="f-maturity" type="date" name="maturity_date" class="input @error('maturity_date') input-error @enderror"
                                   value="{{ old('maturity_date', $asset->maturity_date?->format('Y-m-d')) }}">
                            <p class="help">Deixe em branco se tiver liquidez diária.</p>
                            <x-field-error name="maturity_date" />
                        </div>
                        <div>
                            <label class="label" for="f-inst">Instituição</label>
                            <input id="f-inst" type="text" name="institution" maxlength="120" class="input" placeholder="Ex.: Nubank, Inter, XP"
                                   value="{{ old('institution', $asset->institution) }}">
                            <x-field-error name="institution" />
                        </div>
                    </div>
                </div>
            </template>

            {{-- Fundos / outros --}}
            <template x-if="isManual">
                <div class="space-y-5">
                    <div>
                        <label class="label" for="f-inst2">Instituição</label>
                        <input id="f-inst2" type="text" name="institution" maxlength="120" class="input" placeholder="Ex.: banco ou corretora"
                               value="{{ old('institution', $asset->institution) }}">
                    </div>
                    <x-money-input name="last_price" :value="$lastPrice" label="Valor atual total da posição (opcional)"
                                   help="Confira no app do banco/corretora e atualize de vez em quando. Em branco, usamos o valor aplicado." />
                </div>
            </template>

            @if(household()->isCouple())
                <label class="flex items-center gap-2.5 text-sm text-slate-700">
                    <input type="checkbox" name="is_shared" value="1" class="checkbox" @checked(old('is_shared', $asset->is_shared))>
                    💑 Investimento compartilhado do casal
                </label>
                    <label class="mt-3 flex items-start gap-3">
                        <input type="checkbox" name="is_private" value="1" class="checkbox mt-0.5" @checked(old('is_private', $asset->is_private))>
                        <span class="text-sm"><span class="font-medium text-slate-800">🔒 Investimento privado</span>
                            <span class="block text-xs text-slate-500">Só você vê este ativo.</span></span>
                    </label>
            @endif
        </div>

        @unless($editing)
            <div class="card card-pad space-y-5">
                <div>
                    <div class="card-title">Primeiro aporte</div>
                    <p class="muted text-sm">Opcional — você pode registrar compras e aportes depois, na página do ativo.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="f-first-date">Data</label>
                        <input id="f-first-date" type="date" name="first_date" max="{{ now()->toDateString() }}" class="input"
                               value="{{ old('first_date', now()->toDateString()) }}">
                        <x-field-error name="first_date" />
                    </div>
                </div>
                <template x-if="isMarket">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="f-first-qty">Quantidade</label>
                            <input id="f-first-qty" type="text" inputmode="decimal" name="first_quantity" class="input tabular"
                                   :placeholder="cls === 'crypto' ? 'Ex.: 0,0025' : 'Ex.: 100'" value="{{ old('first_quantity') }}">
                            <p class="help" x-show="cls === 'crypto'">Use vírgula para decimais.</p>
                        </div>
                        <div>
                            <label class="label" for="f-first-price">Preço por unidade (R$)</label>
                            <input id="f-first-price" type="text" inputmode="decimal" name="first_price" class="input tabular"
                                   placeholder="Ex.: 32,50" value="{{ old('first_price') }}">
                        </div>
                    </div>
                </template>
                <template x-if="!isMarket">
                    <x-money-input name="first_amount" label="Valor aplicado" />
                </template>
            </div>
        @endunless

        <div class="flex items-center justify-end gap-2">
            <a href="{{ $editing ? route('investments.show', $asset) : route('investments.index') }}" class="btn-ghost">Cancelar</a>
            <button class="btn-primary">{{ $editing ? 'Salvar alterações' : 'Adicionar à carteira' }}</button>
        </div>
    </form>
</x-layouts.app>
