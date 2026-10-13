@php $toneBox = ['good' => 'from-emerald-600 to-emerald-800', 'neutral' => 'from-[#262622] to-[#0a0a0a]', 'bad' => 'from-rose-600 to-orange-500']; @endphp
<x-layouts.app title="Revisão semanal">
    <x-page-header title="🗓️ Revisão financeira semanal" :subtitle="$r['start']->format('d/m').' a '.$r['end']->format('d/m/Y').' · enviada toda segunda-feira por e-mail/Telegram'">
        <x-slot:actions>
            <a href="{{ route('weekly.index', ['ate' => $r['start']->copy()->subDay()->toDateString()]) }}" class="btn-secondary">← Semana anterior</a>
            @if($r['end']->lt(now()->startOfDay()))<a href="{{ route('weekly.index', ['ate' => $r['end']->copy()->addDays(7)->min(now())->toDateString()]) }}" class="btn-secondary">Próxima →</a>@endif
        </x-slot:actions>
    </x-page-header>

    <div class="rounded-2xl bg-gradient-to-r {{ $toneBox[$r['tone']] }} p-6 text-white shadow-lg">
        <div class="font-display text-xl font-bold sm:text-2xl">{{ $r['headline'] }}</div>
        <div class="mt-4 grid gap-4 text-sm sm:grid-cols-4">
            <div><div class="text-white/70">Gastos</div><div class="text-lg font-bold tabular">{{ brl($r['spent']) }}</div></div>
            <div><div class="text-white/70">Semana anterior</div><div class="text-lg font-bold tabular">{{ brl($r['prev']) }}</div></div>
            <div><div class="text-white/70">Média de 4 semanas</div><div class="text-lg font-bold tabular">{{ brl($r['avg4']) }}</div></div>
            <div><div class="text-white/70">Receitas recebidas</div><div class="text-lg font-bold tabular">{{ brl($r['earned']) }}</div></div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 @3xl:grid-cols-2">
        <div class="card card-pad">
            <div class="card-title">✅ Ações recomendadas</div>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse($r['actions'] as $a)<li class="flex gap-2"><span class="text-brand-600">●</span><span>{{ $a }}</span></li>@empty<li class="text-slate-500">Nada urgente. Aproveite para aportar nas metas! 🎯</li>@endforelse
            </ul>
        </div>
        <div class="card card-pad">
            <div class="card-title">💡 Oportunidades de economia</div>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse($r['opportunities'] as $o)<li>{{ $o }}</li>@empty<li class="text-slate-500">Nenhuma oportunidade clara nesta semana.</li>@endforelse
            </ul>
        </div>
        <div class="card card-pad">
            <div class="card-title">Para onde foi o dinheiro</div>
            <ul class="mt-3 space-y-2">
                @forelse($r['by_category']->take(8) as $c)
                    <li class="flex items-center justify-between text-sm"><span>{{ $c['icon'] }} {{ $c['name'] }}</span><span class="tabular font-medium">{{ brl($c['total']) }}</span></li>
                @empty
                    <li class="text-sm text-slate-500">Nenhum gasto registrado na semana.</li>
                @endforelse
            </ul>
        </div>
        <div class="card card-pad">
            <div class="card-title">O que mudou vs. semana anterior</div>
            <ul class="mt-3 space-y-2">
                @forelse($r['changes'] as $c)
                    <li class="flex items-center justify-between text-sm">
                        <span>{{ $c['icon'] }} {{ $c['name'] }}</span>
                        <span class="tabular font-semibold {{ $c['diff'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $c['diff'] > 0 ? '▲' : '▼' }} {{ brl(abs($c['diff'])) }}</span>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">Sem mudanças relevantes (acima de R$ 30).</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="card mt-4">
        <div class="px-6 pt-5 card-title">Maiores gastos da semana</div>
        <ul class="mt-2 divide-y divide-slate-100">
            @forelse($r['top'] as $t)
                <li class="flex items-center justify-between px-6 py-3 text-sm">
                    <a href="{{ route('transactions.edit', $t) }}" class="hover:underline">{{ $t->category?->icon ?? '•' }} {{ $t->label() }} <span class="text-xs text-slate-400">{{ $t->date->format('d/m') }}</span></a>
                    <span class="tabular font-semibold">{{ brl($t->amount) }}</span>
                </li>
            @empty
                <li class="px-6 py-6 text-sm text-slate-500">Sem gastos na semana.</li>
            @endforelse
        </ul>
    </div>
</x-layouts.app>
