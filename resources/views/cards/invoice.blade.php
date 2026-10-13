@php
    $isCouple = household()->isCouple();
    $isClosed = now()->startOfDay()->gte($closing->copy()->startOfDay());
    $overdue = ! $paid && $total > 0 && now()->startOfDay()->gt($due->copy()->startOfDay());
    if ($paid) {
        [$statusLabel, $statusClass, $statusIcon] = ['Paga', 'badge-green', '✅'];
    } elseif ($overdue) {
        [$statusLabel, $statusClass, $statusIcon] = ['Vencida', 'badge-red', '⚠️'];
    } elseif ($isClosed) {
        [$statusLabel, $statusClass, $statusIcon] = ['Fechada', 'badge-amber', '🔒'];
    } else {
        [$statusLabel, $statusClass, $statusIcon] = ['Aberta', 'badge-sky', '🟢'];
    }
    $color = $card->color ?? '#7c3aed';
    $catTotal = max(1, (float) $byCategory->sum());
@endphp
<x-layouts.app :title="'Fatura · '.$card->name">
    <x-page-header :title="'Fatura '.$card->name" :subtitle="'Fecha dia '.$card->closing_day.' · vence dia '.$card->due_day">
        <x-slot:actions>
            <a href="{{ route('cards.index') }}" class="btn-ghost">← Cartões</a>
            <x-month-nav :month="$month" route="cards.invoice" :params="['card' => $card->id]" />
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 @4xl:grid-cols-3">
        {{-- Resumo --}}
        <div class="card overflow-hidden @4xl:col-span-2">
            <div class="h-1.5" style="background-color: {{ $color }}"></div>
            <div class="card-pad">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-slate-500">Total da fatura de <span class="capitalize">{{ month_name($month) }}</span></span>
                            <span class="{{ $statusClass }}">{{ $statusIcon }} {{ $statusLabel }}</span>
                        </div>
                        <div class="mt-1 font-display text-3xl font-bold tracking-tight tabular {{ $total > 0 ? 'text-slate-900' : 'text-slate-400' }}">{{ brl($total) }}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Fechamento</div>
                            <div class="font-semibold tabular text-slate-700">{{ $closing->format('d/m/Y') }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Vencimento</div>
                            <div class="font-semibold tabular {{ $overdue ? 'text-rose-600' : 'text-slate-700' }}">{{ $due->format('d/m/Y') }}</div>
                        </div>
                    </div>
                </div>

                @if(! $paid && $remaining > 0)
                    <form method="POST" action="{{ route('cards.pay', $card) }}" x-data="{ partial: false }"
                          @submit="if (!confirm(partial ? 'Registrar pagamento parcial? O saldo restante irá para a próxima fatura (rotativo).' : 'Confirmar o pagamento de {{ brl($remaining) }}?')) $event.preventDefault()"
                          class="mt-5 space-y-3 rounded-xl bg-slate-50 p-4">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                        @if($paidAmount > 0)<p class="text-xs text-slate-500">Já pago desta fatura: <b>{{ brl($paidAmount) }}</b> · falta <b>{{ brl($remaining) }}</b></p>@endif
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="flex-1">
                                <label class="label" for="f-pay-account">Pagar com a conta</label>
                                <select id="f-pay-account" name="account_id" class="input" required>
                                    @foreach($accounts as $a)
                                        <option value="{{ $a->id }}" @selected((int) old('account_id', $card->account_id) === $a->id)>🏦 {{ $a->name }}</option>
                                    @endforeach
                                </select>
                                <x-field-error name="account_id" />
                            </div>
                            <div class="flex-1" x-show="partial" x-cloak>
                                <x-money-input name="amount" label="Valor pago" x-bind:disabled="!partial" />
                            </div>
                            <button class="btn-primary" x-text="partial ? '💸 Pagar parte' : '💸 Pagar {{ brl($remaining) }}'"></button>
                        </div>
                        <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" class="checkbox" x-model="partial"> Vou pagar só uma parte agora</label>
                        <p x-show="partial" x-cloak class="text-xs text-amber-700">⚠️ O valor que faltar vai para a próxima fatura como saldo do rotativo — o banco cobra juros altos sobre ele.</p>
                    </form>
                @elseif($paid)
                    <p class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">✅ Fatura paga. Mandou bem! 👏</p>
                @endif
            </div>
        </div>

        @php $parcelados = $items->filter(fn ($t) => $t->installment_group && $t->installment_number < $t->installment_total); @endphp
        @if($parcelados->count())
            <div class="card card-pad">
                <h3 class="card-title">⏩ Antecipar parcelas</h3>
                <p class="muted mt-1">Traga parcelas futuras para a fatura atual (muitos bancos dão desconto dos juros embutidos — informe o desconto que o banco ofereceu).</p>
                <ul class="mt-3 divide-y divide-slate-100">
                    @foreach($parcelados as $t)
                        <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-sm"><span class="font-medium">{{ $t->description }}</span> <span class="text-slate-500">· restam {{ $t->installment_total - $t->installment_number }} parcela(s) de {{ brl($t->amount) }}</span></div>
                            <form method="POST" action="{{ route('cards.anticipate', $t) }}" class="flex items-center gap-2" onsubmit="return confirm('Antecipar estas parcelas para a fatura atual?')">
                                @csrf
                                <input type="number" name="count" min="1" max="{{ $t->installment_total - $t->installment_number }}" value="{{ $t->installment_total - $t->installment_number }}" class="input w-20 py-1.5" aria-label="Quantidade de parcelas">
                                <input type="text" name="discount" placeholder="Desconto R$" inputmode="decimal" class="input w-32 py-1.5" aria-label="Desconto em reais">
                                <button class="btn-secondary btn-sm">Antecipar</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Dica --}}
        <div class="card card-pad border-amber-200 bg-amber-50/60">
            <div class="flex items-center gap-2"><span class="text-lg">💡</span><h3 class="card-title">Nunca pague só o mínimo</h3></div>
            <p class="mt-2 text-sm text-slate-600">
                O que fica para trás entra no <strong>rotativo</strong>, que costuma cobrar <strong>mais de 10% ao mês</strong> — uma das dívidas mais caras do Brasil.
                Se não der para pagar tudo, compare com um parcelamento da fatura ou um empréstimo mais barato.
            </p>
            <a href="{{ route('debts.index') }}" class="mt-3 inline-block text-sm font-medium text-brand-700 hover:underline">Precisa de ajuda com dívidas? →</a>
        </div>
    </div>

    <div class="mt-6 grid gap-4 @4xl:grid-cols-3">
        {{-- Itens --}}
        <div class="card @4xl:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                <h3 class="card-title">Lançamentos</h3>
                <span class="text-xs text-slate-500">{{ $items->count() }} {{ $items->count() === 1 ? 'item' : 'itens' }}</span>
            </div>
            @if($items->isEmpty())
                <x-empty icon="🧾" title="Fatura vazia" text="Nenhuma compra neste cartão para esta fatura." />
            @else
                {{-- Mobile: lista --}}
                <ul class="divide-y divide-slate-100 sm:hidden">
                    @foreach($items as $t)
                        @php $credit = $t->type === 'income'; @endphp
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="text-lg">{{ $t->category?->icon ?? ($credit ? '↩️' : '🧾') }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-slate-800">{{ $t->label() }}</div>
                                <div class="text-xs text-slate-500">{{ $t->date->format('d/m') }} · {{ $t->category?->name ?? 'Sem categoria' }}@if($isCouple && $t->user) · {{ $t->user->name }}@endif</div>
                            </div>
                            <span class="text-sm font-semibold tabular {{ $credit ? 'money-pos' : 'text-slate-800' }}">{{ $credit ? '− ' : '' }}{{ brl($t->amount) }}</span>
                        </li>
                    @endforeach
                </ul>
                {{-- Desktop: tabela --}}
                <div class="hidden overflow-x-auto sm:block">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th>Categoria</th>
                                @if($isCouple)<th>Quem</th>@endif
                                <th class="text-right">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $t)
                                @php $credit = $t->type === 'income'; @endphp
                                <tr>
                                    <td class="tabular text-slate-500">{{ $t->date->format('d/m') }}</td>
                                    <td class="font-medium text-slate-800">
                                        {{ $t->label() }}
                                        @if($credit)<span class="badge-green ml-1">Estorno/crédito</span>@endif
                                    </td>
                                    <td class="text-slate-600">{{ $t->category?->icon }} {{ $t->category?->name ?? 'Sem categoria' }}</td>
                                    @if($isCouple)<td class="text-slate-600">{{ $t->user?->name }}</td>@endif
                                    <td class="text-right font-semibold tabular {{ $credit ? 'money-pos' : 'text-slate-800' }}">{{ $credit ? '− ' : '' }}{{ brl($t->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="{{ $isCouple ? 4 : 3 }}" class="font-semibold text-slate-700">Total</td>
                                <td class="text-right font-bold tabular text-slate-900">{{ brl($total) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

        {{-- Por categoria --}}
        <div class="card card-pad">
            <h3 class="card-title">Por categoria</h3>
            @if($byCategory->isEmpty())
                <p class="mt-3 text-sm text-slate-500">Sem gastos nesta fatura.</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach($byCategory as $name => $sum)
                        <li>
                            <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                                <span class="truncate text-slate-700">{{ $name }}</span>
                                <span class="shrink-0 font-semibold tabular text-slate-800">{{ brl($sum) }}</span>
                            </div>
                            <x-progress :value="$sum / $catTotal * 100" color="bg-violet-500" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-layouts.app>
