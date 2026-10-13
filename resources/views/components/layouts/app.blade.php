@php
    $user = auth()->user();
    $hh = $user->currentHousehold;
    $nav = [
        ['Visão geral', null, [
            ['dashboard', 'Painel', 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
            ['ai.index', 'Consultor IA', 'M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z'],
            ['calendar.index', 'Calendário', 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z'],
            ['reports.index', 'Relatórios', 'M4 20V10M10 20V4M16 20v-7M22 20H2'],
        ]],
        ['Inteligência', null, [
            ['time-machine.index', 'Máquina do Tempo', 'M12 8v4l3 3M3.05 11a9 9 0 11.5 4M3 4v5h5'],
            ['radar.index', 'Radar de problemas', 'M12 12l6-6M21 12a9 9 0 11-9-9M17 12a5 5 0 11-5-5'],
            ['weekly.index', 'Revisão semanal', 'M9 11l3 3 8-8M20 12v7a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2h9'],
        ]],
        ['Dinheiro', null, [
            ['transactions.index', 'Lançamentos', 'M4 6h16M4 12h16M4 18h10'],
            ['recurrences.index', 'Renda & fixos', 'M17 1l4 4-4 4M3 11V9a4 4 0 014-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 01-4 4H3'],
            ['budgets.index', 'Orçamentos', 'M21 12a9 9 0 11-9-9v9z M12 3a9 9 0 019 9h-9z'],
            ['accounts.index', 'Contas', 'M3 10h18M5 6l7-3 7 3M4 10v8M20 10v8M8 14v4M12 14v4M16 14v4M3 21h18'],
            ['cards.index', 'Cartões', 'M2 7a2 2 0 012-2h16a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2zM2 10h20'],
        ]],
        ['Futuro', null, [
            ['debts.index', 'GPS Financeiro', 'M13 2L3 14h9l-1 8 10-12h-9z'],
            ['goals.index', 'Metas', 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 18a6 6 0 100-12 6 6 0 000 12zM12 14a2 2 0 100-4 2 2 0 000 4z'],
            ['investments.index', 'Investimentos', 'M3 17l6-6 4 4 8-8M14 7h7v7'],
        ]],
        ['Mais', null, [
            ['couple.index', $hh?->isCouple() ? 'Casal' : 'Carteira & casal', 'M20.8 4.6a5.5 5.5 0 00-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 00-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 000-7.8z'],
            ['imports.index', 'Importar extrato', 'M12 3v12M7 10l5 5 5-5M5 21h14'],
            ['categories.index', 'Categorias', 'M20.6 13.4l-7.2 7.2a2 2 0 01-2.8 0L2 12V2h10l8.6 8.6a2 2 0 010 2.8zM7 7h.01'],
        ]],
    ];
    $unread = $user->unreadNotifications()->take(8)->get();
    $isActive = fn ($route) => request()->routeIs(\Illuminate\Support\Str::before($route, '.').'.*') || request()->routeIs($route);
    $currentSection = null;
    foreach ($nav as [$sec, $_, $items]) { foreach ($items as [$r]) { if ($isActive($r)) { $currentSection = $sec; break 2; } } }
    $bottomNav = [
        ['dashboard', 'Início', 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
        ['transactions.index', 'Lançamentos', 'M4 6h16M4 12h16M4 18h10'],
        null,
        ['ai.index', 'Consultor', 'M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z'],
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a0a0a">
    <title>{{ isset($title) ? $title.' · ' : '' }}Prumo</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='9' fill='%23F53003'/><path d='M16 6v13' stroke='white' stroke-width='3' stroke-linecap='round'/><path d='M9.5 20.5L16 27l6.5-6.5z' fill='white'/><path d='M10 9h12' stroke='white' stroke-width='2.4' stroke-linecap='round'/></svg>">
    <script>try{var t=localStorage.getItem('prumo-theme')||'system';if(t==='dark'||(t==='system'&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')}catch(e){}</script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased" x-data="{ sidebar: false }">
<a href="#conteudo" class="skip-link btn-primary">Pular para o conteúdo</a>
<div class="min-h-full">
    {{-- Sidebar --}}
    <div x-cloak x-show="sidebar" x-transition.opacity class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden" @click="sidebar = false"></div>
    <aside :class="sidebar ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0'" @keydown.escape.window="sidebar = false"
           class="fixed inset-y-0 left-0 z-50 flex w-[17rem] max-w-[85vw] flex-col lg:w-64 border-r border-white/5 bg-[#0f0f0e] bg-[radial-gradient(90%_40%_at_0%_0%,rgba(245,48,3,.16),transparent_70%)] transition-transform duration-200">
        <div class="flex h-16 items-center gap-2.5 px-5">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5"><x-logo class="h-8 w-8" /><span class="font-display text-xl font-semibold tracking-tight text-white">Prumo</span></a>
            <button type="button" class="ml-auto grid h-9 w-9 place-items-center rounded-lg text-[#a1a09a] hover:bg-white/10 hover:text-white lg:hidden" @click="sidebar = false" aria-label="Fechar menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        @if($hh)
        <div class="mx-3 mb-1 rounded-xl bg-white/[.04] px-3 py-2.5 ring-1 ring-white/10" x-data="{ open: false }">
            <button @click="open = !open" class="flex w-full items-center justify-between text-left">
                <div class="min-w-0">
                    <div class="text-[10.5px] font-semibold uppercase tracking-wider text-[#a1a09a]">{{ $hh->isCouple() ? 'Carteira do casal' : 'Carteira individual' }}</div>
                    <div class="truncate text-sm font-semibold text-white">{{ $hh->name }}</div>
                </div>
                <svg class="h-4 w-4 text-[#a1a09a]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8 9l4-4 4 4M16 15l-4 4-4-4"/></svg>
            </button>
            <div x-cloak x-show="open" x-collapse class="mt-2 space-y-1 border-t border-white/10 pt-2">
                @foreach($user->households as $h)
                    <form method="POST" action="{{ route('household.switch', $h) }}">@csrf
                        <button class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-left text-xs text-[#dcdcd8] hover:bg-white/10">
                            <span>{{ $h->isCouple() ? '💑' : '🙂' }} {{ $h->name }}</span>
                            @if($h->id === $hh->id)<span class="text-brand-500">●</span>@endif
                        </button>
                    </form>
                @endforeach
                <a href="{{ route('onboarding', ['nova' => 1]) }}" class="block rounded-lg px-2 py-1.5 text-xs text-[#a1a09a] hover:bg-white/10 hover:text-white">+ Nova carteira</a>
            </div>
        </div>
        @endif

        <nav class="flex-1 overflow-y-auto px-3 pb-4">
            @foreach($nav as [$section, $_, $items])
                <div class="nav-section">{{ $section }}</div>
                @foreach($items as [$route, $label, $icon])
                    @php $active = $isActive($route); @endphp
                    <a href="{{ route($route) }}" class="nav-link {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif>
                        <svg class="h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        {{ $label }}
                        @if($route === 'ai.index')<span class="ml-auto rounded-md bg-brand-600/20 px-1.5 py-0.5 text-[10px] font-bold text-brand-400">IA</span>@endif
                    </a>
                @endforeach
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-3">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-white/10">
                <div class="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-bold text-white">{{ $user->initials() }}</div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold text-white">{{ $user->name }}</div>
                    <div class="truncate text-xs text-[#a1a09a]">{{ $user->email }}</div>
                </div>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mt-1 lg:hidden">@csrf
                <button class="nav-link w-full">
                    <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> Sair
                </button>
            </form>
        </div>
    </aside>

    <div class="lg:pl-64">
        {{-- Topbar --}}
        <header class="sticky top-0 z-30 flex h-16 items-center gap-2 sm:gap-3 border-b border-slate-200/80 bg-white/75 px-4 backdrop-blur-md sm:px-6 lg:px-8">
            <a href="{{ route('dashboard') }}" class="lg:hidden" aria-label="Painel"><x-logo class="h-8 w-8" /></a>
            <div class="min-w-0 flex-1">
                @if($currentSection)<div class="hidden text-[11px] font-medium uppercase tracking-[0.12em] text-slate-400 sm:block">{{ $currentSection }}</div>@endif
                <h1 class="truncate font-display text-base font-semibold leading-tight tracking-tight text-slate-900 sm:text-[17px]">{{ $title ?? 'Prumo' }}</h1>
            </div>

            <div class="flex items-center gap-1 sm:gap-2" x-data="{ quick: false }">
                <div class="relative hidden lg:block">
                    <button @click="quick = !quick" @click.outside="quick = false" class="btn-primary px-3 sm:px-4">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                        <span class="hidden sm:inline">Lançar</span>
                    </button>
                    <div x-cloak x-show="quick" x-transition.origin.top.right class="absolute right-0 mt-2 w-64 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                        @foreach([
                            ['expense', 'daily', '🛒', 'Gasto do dia a dia', 'Mercado, café, uber...'],
                            ['expense', 'installment', '🧾', 'Compra parcelada', 'Divide em N vezes'],
                            ['income', 'extra_income', '✨', 'Renda extra', 'Freela, venda, bônus'],
                            ['transfer', 'transfer', '🔁', 'Transferência', 'Entre suas contas'],
                        ] as [$t, $k, $i, $l, $d])
                            <a href="{{ route('transactions.create', ['tipo' => $t, 'natureza' => $k]) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-slate-50">
                                <span class="text-xl">{{ $i }}</span>
                                <span><span class="block text-sm font-semibold text-slate-800">{{ $l }}</span><span class="block text-xs text-slate-500">{{ $d }}</span></span>
                            </a>
                        @endforeach
                        <div class="my-1 border-t border-slate-100"></div>
                        <a href="{{ route('recurrences.create', ['tipo' => 'salary']) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-slate-50"><span class="text-xl">💼</span><span class="text-sm font-semibold text-slate-800">Salário fixo</span></a>
                        <a href="{{ route('recurrences.create', ['tipo' => 'fixed']) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-slate-50"><span class="text-xl">📌</span><span class="text-sm font-semibold text-slate-800">Gasto fixo mensal</span></a>
                    </div>
                </div>

                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false" class="btn-ghost relative p-2" aria-label="Notificações">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/></svg>
                        @if($unread->count())<span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white"></span>@endif
                    </button>
                    <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-[min(20rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white shadow-xl">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                            <span class="text-sm font-semibold">Notificações</span>
                            @if($unread->count())
                                <form method="POST" action="{{ route('notifications.read') }}">@csrf<button class="text-xs font-medium text-brand-700 hover:underline">Marcar como lidas</button></form>
                            @endif
                        </div>
                        <div class="max-h-80 overflow-y-auto">
                            @forelse($unread as $n)
                                <a href="{{ $n->data['url'] ?? '#' }}" class="block border-b border-slate-50 px-4 py-3 hover:bg-slate-50">
                                    <div class="text-sm font-semibold text-slate-800">{{ $n->data['title'] ?? 'Aviso' }}</div>
                                    <div class="text-xs text-slate-500">{{ $n->data['body'] ?? '' }}</div>
                                    <div class="mt-1 text-[11px] text-slate-400">{{ $n->created_at->diffForHumans() }}</div>
                                </a>
                            @empty
                                <div class="px-4 py-8 text-center text-sm text-slate-500">Tudo em dia por aqui ✨</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="relative" x-data="{ open: false, theme: window.prumoTheme?.get() ?? 'system' }">
                    <button @click="open = !open" @click.outside="open = false" class="btn-ghost p-2" aria-label="Tema claro ou escuro" :aria-expanded="open">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg>
                    </button>
                    <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-40 rounded-xl border border-slate-200 bg-white p-1 shadow-xl" role="menu">
                        @foreach(['light' => '☀️ Claro', 'dark' => '🌙 Escuro', 'system' => '💻 Sistema'] as $k => $l)
                            <button type="button" role="menuitemradio" :aria-checked="theme === '{{ $k }}'" @click="theme = '{{ $k }}'; window.prumoTheme.set('{{ $k }}'); open = false"
                                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ $l }} <span x-show="theme === '{{ $k }}'">✓</span></button>
                        @endforeach
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="hidden lg:block">@csrf
                    <button class="btn-ghost p-2" title="Sair" aria-label="Sair">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    </button>
                </form>
            </div>
        </header>

        <main id="conteudo" tabindex="-1" class="@container fade-in mx-auto w-full max-w-[90rem] px-4 pt-5 pb-28 focus:outline-none sm:px-6 sm:pt-6 lg:px-8 lg:pb-10">
            @if($hh && ! can_write())
                <div class="mb-5 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="status">👀 Você tem acesso <b>somente leitura</b> a esta carteira: pode ver tudo e conversar com o consultor, mas não alterar dados.</div>
            @endif
            <x-flash />
            {{ $slot }}
        </main>
    </div>

    {{-- Navegação inferior (celular) --}}
    <nav class="bottom-nav lg:hidden" aria-label="Navegação rápida" x-data="{ quick: false }" @keydown.escape.window="quick = false">
        <div x-cloak x-show="quick" x-transition.opacity class="fixed inset-0 -z-10 bg-black/40 backdrop-blur-[2px]" @click="quick = false"></div>
        <div x-cloak x-show="quick" x-transition.origin.bottom class="absolute inset-x-3 bottom-[calc(100%+.5rem)] rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl">
            @foreach([
                ['transactions.create', ['tipo' => 'expense', 'natureza' => 'daily'], '🛒', 'Gasto do dia a dia'],
                ['transactions.create', ['tipo' => 'expense', 'natureza' => 'installment'], '🧾', 'Compra parcelada'],
                ['transactions.create', ['tipo' => 'income', 'natureza' => 'extra_income'], '✨', 'Renda extra'],
                ['transactions.create', ['tipo' => 'transfer', 'natureza' => 'transfer'], '🔁', 'Transferência'],
                ['recurrences.create', ['tipo' => 'salary'], '💼', 'Salário fixo'],
                ['recurrences.create', ['tipo' => 'fixed'], '📌', 'Gasto fixo mensal'],
            ] as [$r, $p, $i, $l])
                <a href="{{ route($r, $p) }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50"><span class="text-lg">{{ $i }}</span>{{ $l }}</a>
            @endforeach
        </div>
        <div class="grid grid-cols-5 items-end">
            @foreach($bottomNav as $item)
                @if($item === null)
                    <div class="flex justify-center">
                        <button type="button" @click="quick = !quick" :aria-expanded="quick" aria-label="Novo lançamento"
                                class="-mt-6 grid h-14 w-14 place-items-center rounded-2xl bg-brand-700 text-white shadow-[0_10px_24px_-8px_rgba(245,48,3,.7)] ring-4 ring-[#fdfdfc] transition active:scale-95 dark:ring-[#0a0a0a]">
                            <svg class="h-6 w-6 transition" :class="quick && 'rotate-45'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                        </button>
                    </div>
                @else
                    @php [$r, $l, $ic] = $item; $on = $isActive($r); @endphp
                    <a href="{{ route($r) }}" class="bottom-nav-item {{ $on ? 'active' : '' }}" @if($on) aria-current="page" @endif>
                        <svg class="h-[22px] w-[22px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $ic }}"/></svg>
                        <span>{{ $l }}</span>
                    </a>
                @endif
            @endforeach
            <button type="button" class="bottom-nav-item" @click="sidebar = true" aria-label="Abrir menu completo">
                <svg class="h-[22px] w-[22px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                <span>Menu</span>
            </button>
        </div>
    </nav>
</div>
@stack('scripts')
</body>
</html>
