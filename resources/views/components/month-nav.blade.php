@props(['month', 'route', 'params' => []])
<div class="inline-flex items-center rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
    <a href="{{ route($route, array_merge($params, ['mes' => $month->copy()->subMonthNoOverflow()->format('Y-m')])) }}" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Mês anterior">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <a href="{{ route($route, array_merge($params, ['mes' => now()->format('Y-m')])) }}" class="min-w-[130px] px-2 text-center text-sm font-semibold capitalize text-slate-800">
        {{ month_name($month) }} {{ $month->year }}
    </a>
    <a href="{{ route($route, array_merge($params, ['mes' => $month->copy()->addMonthNoOverflow()->format('Y-m')])) }}" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Próximo mês">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
    </a>
</div>
