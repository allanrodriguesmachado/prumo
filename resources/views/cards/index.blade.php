@php $isCouple = household()->isCouple(); @endphp
<x-layouts.app title="Cartões">
    <x-page-header title="Cartões de crédito" subtitle="Limite, fatura atual e vencimento de cada cartão.">
        <x-slot:actions><a href="{{ route('cards.create') }}" class="btn-primary">+ Novo cartão</a></x-slot:actions>
    </x-page-header>

    @if($cards->isEmpty())
        <div class="card">
            <x-empty icon="💳" title="Nenhum cartão cadastrado" text="Cadastre seus cartões para acompanhar a fatura em tempo real e nunca ser pego de surpresa no vencimento.">
                <a href="{{ route('cards.create') }}" class="btn-primary">+ Cadastrar cartão</a>
            </x-empty>
        </div>
    @else
        <div class="grid gap-5 md:grid-cols-2 @5xl:grid-cols-3">
            @foreach($cards as $card)
                @php
                    $limit = (float) $card->limit;
                    $u = (float) ($used[$card->id] ?? 0);
                    $avail = max(0, $limit - $u);
                    $util = $limit > 0 ? $u / $limit * 100 : 0;
                    $inv = $current[$card->id];
                    $tot = (float) ($totals[$card->id] ?? 0);
                    $due = $card->dueDateFor($inv);
                    $color = $card->color ?? '#7c3aed';
                @endphp
                <div class="card overflow-hidden {{ $card->archived ? 'opacity-60' : '' }}">
                    {{-- Visual do cartão --}}
                    <div class="relative m-4 mb-0 aspect-[1.75] max-h-48 overflow-hidden rounded-2xl p-5 text-white shadow-lg"
                         style="background: linear-gradient(135deg, {{ $color }} 0%, {{ $color }}cc 55%, #0f172a 140%)">
                        <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10"></div>
                        <div class="absolute -bottom-16 -left-6 h-40 w-40 rounded-full bg-white/5"></div>
                        <div class="relative flex h-full flex-col justify-between">
                            <div class="flex items-start justify-between">
                                <div class="h-7 w-10 rounded-md bg-gradient-to-br from-amber-200 to-amber-400/80 ring-1 ring-black/10"></div>
                                <span class="text-sm font-bold italic tracking-wide opacity-90">{{ $card->brand ?: 'Crédito' }}</span>
                            </div>
                            <div>
                                <div class="font-display text-lg font-semibold leading-tight">{{ $card->name }}</div>
                                <div class="mt-1 flex items-center justify-between text-[11px] uppercase tracking-wider text-white/75">
                                    <span>Fecha dia {{ $card->closing_day }}</span>
                                    <span>Vence dia {{ $card->due_day }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-pad">
                        <div class="flex flex-wrap gap-1.5">
                            @if($isCouple && $card->is_shared)<span class="badge-violet">💑 Compartilhado</span>@endif
                            @if($isCouple && ! $card->is_shared && $card->user)<span class="badge-sky">{{ $card->user->name }}</span>@endif
                            @if($card->account)<span class="badge-slate">Paga com {{ $card->account->name }}</span>@endif
                            @if($card->archived)<span class="badge-amber">Arquivado</span>@endif
                        </div>

                        {{-- Fatura atual --}}
                        <div class="mt-4 flex items-end justify-between gap-3">
                            <div>
                                <div class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Fatura de <span class="capitalize">{{ month_name($inv) }}</span></div>
                                <div class="mt-0.5 font-display text-2xl font-bold tabular {{ $tot > 0 ? 'text-slate-900' : 'text-slate-400' }}">{{ brl($tot) }}</div>
                                <div class="text-xs text-slate-500">
                                    Vence {{ $due->format('d/m') }}
                                    @if($due->isFuture())
                                        · em {{ (int) now()->startOfDay()->diffInDays($due->copy()->startOfDay()) }} dia(s)
                                    @endif
                                </div>
                            </div>
                            <a href="{{ route('cards.invoice', $card) }}" class="btn-secondary btn-sm">Ver fatura →</a>
                        </div>

                        {{-- Limite --}}
                        <div class="mt-5 border-t border-slate-100 pt-4">
                            <div class="mb-1.5 flex items-center justify-between text-xs">
                                <span class="font-medium text-slate-600">Limite usado</span>
                                <span class="tabular text-slate-500">{{ pct($util, 0) }}</span>
                            </div>
                            <x-progress :value="$util" :color="$util >= 80 ? 'bg-rose-500' : ($util > 30 ? 'bg-amber-500' : 'bg-emerald-500')" />
                            <div class="mt-2 grid grid-cols-3 gap-2 text-xs">
                                <div><div class="text-slate-400">Limite</div><div class="font-semibold tabular text-slate-700">{{ brl($limit) }}</div></div>
                                <div><div class="text-slate-400">Usado</div><div class="font-semibold tabular money-neg">{{ brl($u) }}</div></div>
                                <div><div class="text-slate-400">Disponível</div><div class="font-semibold tabular money-pos">{{ brl($avail) }}</div></div>
                            </div>
                            @if($util > 30 && ! $card->archived)
                                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    💡 Usar mais de 30% do limite pode pesar no seu score de crédito. Se puder, segure novas compras neste cartão.
                                </p>
                            @endif
                        </div>

                        <div class="mt-4 flex items-center justify-end gap-1">
                            <a href="{{ route('cards.edit', $card) }}" class="btn-ghost btn-sm">✏️ Editar</a>
                            <form method="POST" action="{{ route('cards.destroy', $card) }}" onsubmit="return confirm('Excluir este cartão? Se ele tiver lançamentos, será apenas arquivado.')">
                                @csrf @method('DELETE')
                                <button class="btn-ghost btn-sm text-rose-600">🗑️ Excluir</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
