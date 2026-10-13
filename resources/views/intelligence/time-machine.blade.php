@php
    $type = old('type', $input['type'] ?? request('type', 'purchase'));
    $colors = ['#706f6c', '#f53003', '#7c3aed', '#f59e0b'];
    $badge = ['viável' => 'badge-green', 'apertado' => 'badge-amber', 'inviável' => 'badge-red'];
@endphp
<x-layouts.app title="Máquina do Tempo">
    <x-page-header title="⏳ Máquina do Tempo financeira" subtitle="Antes de decidir, veja como suas finanças ficam nos próximos 12 meses. Nada é gravado — é só simulação." />

    <div class="grid gap-6 @4xl:grid-cols-3">
        <form method="GET" action="{{ route('time-machine.index') }}" class="card card-pad space-y-4 self-start" x-data="{ type: @js($type) }">
            <div>
                <label class="label">O que você quer simular?</label>
                <div class="grid grid-cols-2 gap-2">
                    @foreach(['purchase' => '🛍️ Uma compra', 'recurring_expense' => '📌 Nova despesa mensal', 'recurring_income' => '💼 Nova renda mensal', 'income_loss' => '📉 Perder renda'] as $k => $l)
                        <label class="cursor-pointer rounded-xl border px-3 py-2 text-center text-sm transition" :class="type === '{{ $k }}' ? 'border-brand-600 bg-brand-50 font-semibold text-brand-800' : 'border-slate-200 text-slate-600 hover:border-slate-300'">
                            <input type="radio" name="type" value="{{ $k }}" x-model="type" class="sr-only">{{ $l }}
                        </label>
                    @endforeach
                </div>
            </div>
            <x-money-input name="amount" :value="$input['amount'] ?? request('amount')" label="Valor" required />
            <p class="help -mt-2" x-show="type === 'purchase'">Valor <b>total</b> da compra (com juros, se houver).</p>
            <p class="help -mt-2" x-show="type !== 'purchase'" x-cloak>Valor por mês.</p>
            <div class="grid grid-cols-2 gap-3" x-show="type === 'purchase'">
                <div>
                    <label class="label" for="installments">Parcelas</label>
                    <input id="installments" type="number" name="installments" min="1" max="48" value="{{ $input['installments'] ?? request('installments', 10) }}" class="input" x-bind:disabled="type !== 'purchase'">
                </div>
                <div>
                    <label class="label" for="card_id">Pagar com</label>
                    <select id="card_id" name="card_id" class="input" x-bind:disabled="type !== 'purchase'">
                        <option value="">Conta / boleto</option>
                        @foreach($cards as $c)<option value="{{ $c->id }}" @selected(($input['card_id'] ?? null) == $c->id)>💳 {{ $c->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div x-show="type === 'recurring_expense' || type === 'recurring_income'" x-cloak>
                <label class="label" for="months">Por quantos meses?</label>
                <input id="months" type="number" name="months" min="1" max="12" value="{{ $input['months'] ?? 12 }}" class="input" x-bind:disabled="!(type === 'recurring_expense' || type === 'recurring_income')">
            </div>
            <div>
                <label class="label" for="start">A partir de</label>
                <input id="start" type="month" name="start" value="{{ isset($input['start']) ? substr($input['start'], 0, 7) : now()->format('Y-m') }}" min="{{ now()->format('Y-m') }}" class="input">
            </div>
            <button class="btn-primary w-full">Simular</button>
            <p class="text-xs text-slate-500">Dica: você também pode perguntar ao <a href="{{ route('ai.index') }}" class="font-semibold text-brand-700 hover:underline">Consultor IA</a>: "Se eu comprar algo de R$ 2.000 em 10x, como fico?"</p>
        </form>

        <div class="space-y-4 @4xl:col-span-2">
            @if(! $result)
                <div class="card">
                    <x-empty icon="⏳" title="Faça uma pergunta ao seu futuro" text="Ex.: um celular de R$ 2.000 em 10x no cartão, uma academia de R$ 120/mês ou um freela extra de R$ 800/mês. O Prumo compara com a sua projeção atual e mostra o veredito." />
                </div>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="card card-pad">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Hoje, sem a decisão</div>
                        <div class="mt-1 text-sm text-slate-600">Menor saldo</div>
                        <div class="font-display text-xl font-bold tabular {{ $result['baseline']['min_balance'] < 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ brl($result['baseline']['min_balance']) }}</div>
                        <div class="text-xs text-slate-500">em {{ $result['baseline']['min_month'] }}</div>
                    </div>
                    @foreach($result['scenarios'] as $key => $s)
                        <div class="card card-pad {{ $result['best'] === $key ? 'ring-2 ring-brand-500' : '' }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="{{ $badge[$s['verdict']] ?? 'badge-slate' }}">{{ $s['verdict'] }}</span>
                                @if($result['best'] === $key)<span class="text-[11px] font-semibold text-brand-700">melhor opção</span>@endif
                            </div>
                            <div class="mt-2 text-sm font-semibold text-slate-800">{{ $s['label'] }}</div>
                            <div class="mt-1 text-xs text-slate-600">{{ $s['explanation'] }}</div>
                            <div class="mt-3 text-xs text-slate-500">Menor saldo <b class="tabular {{ $s['min_balance'] < 0 ? 'text-rose-600' : 'text-slate-800' }}">{{ brl($s['min_balance']) }}</b> · em 12 meses <b class="tabular">{{ brl($s['delta_end'], true) }}</b></div>
                            @if($s['commitment_pct'])<div class="text-xs text-slate-500">Compromete <b>{{ pct($s['commitment_pct'], 0) }}</b> da renda no mês mais pesado</div>@endif
                            @if($s['note'])<div class="mt-2 text-[11px] text-slate-400">{{ $s['note'] }}</div>@endif
                        </div>
                    @endforeach
                </div>

                <div class="card card-pad">
                    <div class="card-title">Saldo projetado mês a mês</div>
                    <div class="mt-4 h-72">
                        <x-chart :config="[
                            'type' => 'line',
                            'data' => [
                                'labels' => array_column($result['baseline']['rows'], 'label'),
                                'datasets' => array_merge(
                                    [['label' => 'Sem a decisão', 'data' => array_column($result['baseline']['rows'], 'balance'), 'borderColor' => $colors[0], 'borderDash' => [6, 4], 'tension' => 0.3, 'pointRadius' => 2]],
                                    collect($result['scenarios'])->values()->map(fn ($s, $i) => ['label' => $s['label'], 'data' => array_column($s['rows'], 'balance'), 'borderColor' => $colors[($i + 1) % 4], 'backgroundColor' => $colors[($i + 1) % 4].'22', 'tension' => 0.3, 'pointRadius' => 2])->all()
                                ),
                            ],
                        ]" aria-label="Gráfico de saldo projetado por cenário" />
                    </div>
                </div>

                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>Mês</th><th class="text-right">Sem a decisão</th>@foreach($result['scenarios'] as $s)<th class="text-right">{{ \Illuminate\Support\Str::limit($s['label'], 28) }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach($result['baseline']['rows'] as $i => $row)
                                    <tr>
                                        <td class="font-medium">{{ $row['label'] }}</td>
                                        <td class="text-right tabular {{ $row['balance'] < 0 ? 'text-rose-600' : '' }}">{{ brl($row['balance']) }}</td>
                                        @foreach($result['scenarios'] as $s)<td class="text-right tabular {{ $s['rows'][$i]['balance'] < 0 ? 'font-semibold text-rose-600' : '' }}">{{ brl($s['rows'][$i]['balance']) }}</td>@endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card card-pad">
                    <div class="text-sm font-semibold text-slate-800">Premissas desta simulação</div>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-600">@foreach($result['assumptions'] as $a)<li>{{ $a }}</li>@endforeach</ul>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
