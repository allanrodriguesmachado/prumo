@props(['name', 'title', 'maxWidth' => 'max-w-lg'])
<div x-data="{ open: false }" x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true" x-on:keydown.escape.window="open = false">
    <div x-cloak x-show="open" class="fixed inset-0 z-[60] flex items-end justify-center p-0 sm:items-center sm:p-4">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-900/50" @click="open = false"></div>
        <div x-show="open" x-transition class="relative w-full {{ $maxWidth }} rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="font-display text-base font-semibold">{{ $title }}</h3>
                <button type="button" @click="open = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">✕</button>
            </div>
            <div class="px-6 py-5">{{ $slot }}</div>
        </div>
    </div>
</div>
