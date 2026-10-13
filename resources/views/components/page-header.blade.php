@props(['title', 'subtitle' => null])
<div class="mb-5 flex flex-col gap-3 sm:mb-7 sm:flex-row sm:items-end sm:justify-between sm:gap-4">
    <div class="min-w-0">
        <h2 class="font-display text-[1.6rem] font-semibold leading-tight tracking-tight text-slate-900 sm:text-3xl">{{ $title }}</h2>
        @if($subtitle)<p class="mt-1 max-w-2xl text-sm text-slate-500">{{ $subtitle }}</p>@endif
    </div>
    @if(isset($actions))<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endif
</div>
