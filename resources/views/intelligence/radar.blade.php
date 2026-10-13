@php
    $styles = ['critical' => ['border-rose-200 bg-rose-50/70', 'badge-red', 'Crítico'], 'warning' => ['border-amber-200 bg-amber-50/70', 'badge-amber', 'Atenção'], 'info' => ['border-sky-200 bg-sky-50/60', 'badge-sky', 'Informativo']];
    $neg = collect($projection)->firstWhere('balance', '<', 0);
@endphp
<x-layouts.app title="Radar de problemas">
    <x-page-header title="📡 Radar de problemas" subtitle="O Prumo vigia seus números e avisa antes que um problema vire dívida." />

    <div class="stat-grid sm:grid-cols-3">
        <x-stat label="Alertas críticos" :value="$alerts->where('severity', 'critical')->count()" :tone="$alerts->where('severity', 'critical')->count() ? 'red' : 'green'" icon="🚨" />
        <x-stat label="Pontos de atenção" :value="$alerts->where('severity', 'warning')->count()" :tone="$alerts->where('severity', 'warning')->count() ? 'amber' : 'green'" icon="⚠️" />
        <x-stat label="Gastos fixos / renda" :value="$income > 0 ? pct($fixed / $income * 100, 0) : '—'" icon="⚖️" :hint="brl($fixed).' de '.brl($income).' por mês'" />
    </div>

    <div class="mt-6 space-y-3">
        @forelse($alerts as $a)
            @php [$box, $badge, $label] = $styles[$a['severity']]; @endphp
            <div class="flex flex-col gap-3 rounded-2xl border p-5 sm:flex-row sm:items-center {{ $box }}" role="alert">
                <div class="text-3xl">{{ $a['icon'] }}</div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2"><span class="{{ $badge }}">{{ $label }}</span><span class="font-semibold text-slate-900">{{ $a['title'] }}</span></div>
                    <p class="mt-1 text-sm text-slate-700">{{ $a['text'] }}</p>
                    <p class="mt-1 text-sm text-slate-500">👉 {{ $a['action'] }}</p>
                </div>
                <a href="{{ $a['url'] }}" class="btn-secondary shrink-0">Resolver</a>
            </div>
        @empty
            <div class="card"><x-empty icon="🛰️" title="Nenhum problema no radar" text="Saldo projetado positivo, renda estável e compromissos sob controle. Continue lançando seus gastos para o radar enxergar tudo." /></div>
        @endforelse
    </div>

    <div class="card card-pad mt-6">
        <div class="flex items-center justify-between">
            <div class="card-title">Saldo projetado — 6 meses</div>
            @if($neg)<span class="badge-red">negativo em {{ $neg['label'] }}</span>@endif
        </div>
        <div class="mt-4 h-60">
            <x-chart :config="[
                'type' => 'bar',
                'data' => ['labels' => array_column($projection, 'label'), 'datasets' => [[
                    'label' => 'Saldo projetado', 'data' => array_column($projection, 'balance'), 'borderRadius' => 6,
                    'backgroundColor' => array_map(fn ($r) => $r['balance'] < 0 ? '#f43f5e' : '#10b981', $projection),
                ]]],
                'options' => ['plugins' => ['legend' => ['display' => false]]],
            ]" aria-label="Saldo projetado para os próximos 6 meses" />
        </div>
        <p class="mt-3 text-xs text-slate-500">Regras do radar: saldo negativo projetado, parcelas acima de 25% da renda, renda comprometida acima de 70%, queda de renda acima de 20%, categoria 40% acima da média, gasto fixo 30% acima do habitual, cobranças recorrentes não cadastradas, vencimentos em 3 dias e dinheiro livre negativo.</p>
    </div>
</x-layouts.app>
