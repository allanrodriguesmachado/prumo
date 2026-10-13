@php
    $p = $position;
    $pricing = $asset->pricing();
    $isMarket = in_array($pricing, ['variable', 'crypto']);
    $types = \App\Models\AssetOperation::TYPES;
    $qtyDec = $pricing === 'crypto' ? 8 : 4;
    $fmtQty = fn ($q) => num($q, fmod((float) $q, 1) == 0 ? 0 : $qtyDec);

    $r = $asset->rate !== null ? num($asset->rate, fmod((float) $asset->rate, 1) == 0 ? 0 : 2) : null;
    $rateLabel = match ($asset->indexer) {
        'cdi' => $r !== null ? "{$r}% do CDI" : 'CDI',
        'selic' => $r !== null && (float) $asset->rate > 0 ? "Selic + {$r}%" : 'Selic',
        'ipca' => $r !== null ? "IPCA + {$r}%" : 'IPCA +',
        'prefixed' => $r !== null ? "{$r}% a.a." : 'Prefixado',
        'savings' => 'Regra da poupança',
        default => $asset->class === 'savings' ? 'Regra da poupança' : null,
    };
    $gainHint = '<span class="font-semibold '.($p['gain'] >= 0 ? 'text-emerald-600' : 'text-rose-600').'">'.($p['gain_pct'] >= 0 ? '+' : '').pct($p['gain_pct'], 2).'</span> sobre o custo';
    $typeBadge = ['buy' => 'badge-sky', 'sell' => 'badge-amber', 'dividend' => 'badge-violet', 'jcp' => 'badge-violet', 'yield' => 'badge-green'];
    $ops = $asset->operations->sortByDesc(fn ($o) => $o->date->format('Ymd').str_pad($o->id, 10, '0', STR_PAD_LEFT));
@endphp

<x-layouts.app :title="$asset->name">
    <x-page-header :title="$asset->name">
        <x-slot:actions>
            <a href="{{ route('investments.index') }}" class="btn-ghost">← Carteira</a>
            <a href="{{ route('investments.edit', $asset) }}" class="btn-secondary">Editar</a>
            <form method="POST" action="{{ route('investments.destroy', $asset) }}" onsubmit="return confirm('Remover este ativo e todas as suas operações? Essa ação não pode ser desfeita.')">
                @csrf @method('DELETE')
                <button class="btn-ghost text-rose-600 hover:bg-rose-50">Excluir</button>
            </form>
            <button type="button" class="btn-primary" @click="$dispatch('open-modal', 'new-op')">+ Operação</button>
        </x-slot:actions>
    </x-page-header>

    <div class="-mt-4 mb-6 flex flex-wrap items-center gap-2 text-sm">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
            <span class="h-2 w-2 rounded-full" style="background: {{ $asset->classColor() }}"></span>{{ $asset->classLabel() }}
        </span>
        @if($asset->ticker)<span class="badge-slate font-mono">{{ $asset->ticker }}</span>@endif
        @if($rateLabel && $pricing === 'fixed')<span class="badge-green">{{ $rateLabel }}</span>@endif
        @if($asset->maturity_date)<span class="badge-slate">Vence em {{ $asset->maturity_date->format('d/m/Y') }}</span>@endif
        @if($asset->institution)<span class="badge-slate">🏦 {{ $asset->institution }}</span>@endif
        @if($asset->is_shared && household()->isCouple())<span class="badge-violet">💑 Do casal</span>@endif
    </div>

    <div class="stat-grid @3xl:grid-cols-4">
        <x-stat label="Valor atual" :value="brl($p['value'])" tone="brand" icon="💼"
                :hint="$pricing === 'manual' ? 'Valor informado manualmente' : ($pricing === 'fixed' ? 'Estimativa bruta, antes do IR' : null)" />
        <x-stat label="Custo" :value="brl($p['cost'])" icon="🪙"
                :hint="$isMarket ? $fmtQty($p['quantity']).' un. · PM '.brl($p['avg_price']) : 'Total aplicado na posição atual'" />
        <x-stat label="Resultado" :value="brl($p['gain'], true)" :tone="$p['gain'] >= 0 ? 'green' : 'red'" :icon="$p['gain'] >= 0 ? '📈' : '📉'" :hint="$gainHint" />
        <x-stat label="Proventos recebidos" :value="brl($p['income'])" tone="violet" icon="🎁"
                :hint="$p['realized'] != 0 ? 'Lucro realizado em vendas: '.brl($p['realized'], true) : 'Dividendos, JCP e rendimentos'" />
    </div>

    @if($isMarket)
        <div class="card card-pad mt-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-xs text-slate-500">Última cotação</div>
                <div class="flex items-center gap-2">
                    <span class="font-display text-xl font-bold tabular">{{ $asset->last_price !== null ? brl($asset->last_price) : '—' }}</span>
                    @if($asset->day_change !== null)
                        <span class="{{ $asset->day_change >= 0 ? 'badge-green' : 'badge-red' }}">{{ $asset->day_change >= 0 ? '+' : '' }}{{ pct($asset->day_change, 2) }} hoje</span>
                    @endif
                </div>
                <div class="text-xs text-slate-400">{{ $asset->price_updated_at ? 'Atualizada '.$asset->price_updated_at->diffForHumans() : 'Ainda sem cotação' }}</div>
            </div>
            <form method="POST" action="{{ route('investments.refresh') }}">
                @csrf
                <button class="btn-secondary btn-sm">🔄 Atualizar cotações</button>
            </form>
        </div>
    @elseif($pricing === 'fixed')
        <p class="help mt-3">💡 Valor bruto estimado antes do IR, calculado com dados oficiais do Banco Central.</p>
    @elseif($pricing === 'manual')
        <p class="help mt-3">💡 Para atualizar o valor da posição, edite o ativo e informe o valor atual total que aparece no seu banco ou corretora.</p>
    @endif

    {{-- Operações --}}
    <div class="card mt-6 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4">
            <h3 class="card-title">Operações</h3>
            <button type="button" class="btn-secondary btn-sm" @click="$dispatch('open-modal', 'new-op')">+ Registrar</button>
        </div>

        @if($ops->isEmpty())
            <x-empty icon="🧾" title="Nenhuma operação ainda" text="Registre compras, vendas e proventos para acompanhar a rentabilidade real.">
                <button type="button" class="btn-primary" @click="$dispatch('open-modal', 'new-op')">Registrar primeira operação</button>
            </x-empty>
        @else
            {{-- Mobile --}}
            <ul class="divide-y divide-slate-100 border-t border-slate-100 md:hidden">
                @foreach($ops as $op)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <span class="{{ $typeBadge[$op->type] ?? 'badge-slate' }}">{{ $types[$op->type] ?? $op->type }}</span>
                            <div class="mt-1 text-xs text-slate-500">
                                {{ $op->date->format('d/m/Y') }}
                                @if($isMarket && in_array($op->type, ['buy', 'sell']))· {{ $fmtQty($op->quantity) }} × {{ brl($op->price) }}@endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold tabular {{ $op->type === 'sell' ? 'text-amber-700' : ($op->type === 'buy' ? 'text-slate-800' : 'money-pos') }}">{{ brl($op->total) }}</span>
                            <form method="POST" action="{{ route('investments.operations.destroy', $op) }}" onsubmit="return confirm('Excluir esta operação?')">
                                @csrf @method('DELETE')
                                <button class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Excluir" aria-label="Excluir operação">✕</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- Desktop --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Data</th>
                        @if($isMarket)
                            <th class="text-right">Quantidade</th>
                            <th class="text-right">Preço</th>
                            <th class="text-right">Taxas</th>
                        @endif
                        <th class="text-right">Total</th>
                        <th class="w-10"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($ops as $op)
                        @php $trade = in_array($op->type, ['buy', 'sell']); @endphp
                        <tr>
                            <td><span class="{{ $typeBadge[$op->type] ?? 'badge-slate' }}">{{ $types[$op->type] ?? $op->type }}</span></td>
                            <td class="tabular">{{ $op->date->format('d/m/Y') }}</td>
                            @if($isMarket)
                                <td class="text-right tabular">{{ $trade ? $fmtQty($op->quantity) : '—' }}</td>
                                <td class="text-right tabular">{{ $trade ? brl($op->price) : '—' }}</td>
                                <td class="text-right tabular text-slate-500">{{ $op->fees > 0 ? brl($op->fees) : '—' }}</td>
                            @endif
                            <td class="text-right font-semibold tabular {{ $trade ? '' : 'money-pos' }}">{{ brl($op->total) }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('investments.operations.destroy', $op) }}" onsubmit="return confirm('Excluir esta operação?')">
                                    @csrf @method('DELETE')
                                    <button class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Excluir" aria-label="Excluir operação">✕</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Nova operação --}}
    <x-modal name="new-op" title="Registrar operação">
        <form method="POST" action="{{ route('investments.operations.store', $asset) }}" class="space-y-4"
              x-data="{
                  type: @js(old('type', 'buy')),
                  market: @js($isMarket),
                  qty: @js(old('quantity', '')),
                  price: @js(old('price', '')),
                  fees: @js(old('fees', '')),
                  get detailed() { return this.market && ['buy', 'sell'].includes(this.type) },
                  n(v) { v = String(v || '').trim(); if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.'); return Number(v) || 0 },
                  get preview() {
                      const base = this.n(this.qty) * this.n(this.price);
                      return this.type === 'buy' ? base + this.n(this.fees) : base - this.n(this.fees);
                  },
              }">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="op-type">Tipo</label>
                    <select id="op-type" name="type" class="input" x-model="type">
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-field-error name="type" />
                </div>
                <div>
                    <label class="label" for="op-date">Data</label>
                    <input id="op-date" type="date" name="date" required max="{{ now()->toDateString() }}" class="input"
                           value="{{ old('date', now()->toDateString()) }}">
                    <x-field-error name="date" />
                </div>
            </div>

            <template x-if="detailed">
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="op-qty">Quantidade</label>
                            <input id="op-qty" type="text" inputmode="decimal" name="quantity" required class="input tabular" x-model="qty"
                                   placeholder="{{ $pricing === 'crypto' ? 'Ex.: 0,0025' : 'Ex.: 100' }}">
                        </div>
                        <div>
                            <label class="label" for="op-price">Preço por unidade (R$)</label>
                            <input id="op-price" type="text" inputmode="decimal" name="price" required class="input tabular" x-model="price" placeholder="Ex.: 32,50">
                        </div>
                    </div>
                    <div>
                        <label class="label" for="op-fees">Taxas e corretagem (R$)</label>
                        <input id="op-fees" type="text" inputmode="decimal" name="fees" class="input tabular" x-model="fees" placeholder="0,00">
                        <p class="help">Use vírgula para decimais.</p>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm">
                        <span class="text-slate-500">Total da operação</span>
                        <span class="font-semibold tabular" x-text="brl(preview)"></span>
                    </div>
                </div>
            </template>
            <template x-if="!detailed">
                <div>
                    <x-money-input name="total" required
                                   label="{{ $pricing === 'fixed' || $pricing === 'manual' ? 'Valor' : 'Valor recebido' }}" />
                    <p class="help" x-show="type === 'sell'">Informe o valor bruto resgatado.</p>
                    <p class="help" x-show="['dividend', 'jcp', 'yield'].includes(type)">Valor líquido que caiu na sua conta. 🎉</p>
                </div>
            </template>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn-ghost" @click="open = false">Cancelar</button>
                <button class="btn-primary">Registrar</button>
            </div>
        </form>
    </x-modal>

    @if($errors->hasAny(['type', 'date', 'quantity', 'price', 'fees', 'total']))
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'new-op'))"></div>
    @endif
</x-layouts.app>
