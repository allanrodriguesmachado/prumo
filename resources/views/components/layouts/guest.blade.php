<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-[#fdfdfc]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}Prumo</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='9' fill='%23F53003'/><path d='M16 6v13' stroke='white' stroke-width='3' stroke-linecap='round'/><path d='M9.5 20.5L16 27l6.5-6.5z' fill='white'/><path d='M10 9h12' stroke='white' stroke-width='2.4' stroke-linecap='round'/></svg>">
    <script>try{var t=localStorage.getItem('prumo-theme')||'system';if(t==='dark'||(t==='system'&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')}catch(e){}</script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased dark:bg-[#0a0a0a]">
<div class="grid min-h-full lg:grid-cols-2">
    <div class="flex flex-col justify-center px-6 py-12 sm:px-12 lg:px-20">
        <div class="mx-auto w-full max-w-sm">
            <a href="{{ route('home') }}" class="mb-10 flex items-center gap-2.5">
                <x-logo class="h-9 w-9" />
                <span class="font-display text-2xl font-semibold tracking-tight text-slate-900">Prumo</span>
            </a>
            @if(session('status'))
                <div class="mb-6 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-800 ring-1 ring-brand-200">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </div>
    </div>
    <div class="hero relative hidden overflow-hidden lg:block">
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.035)_1px,transparent_1px)] bg-[size:44px_44px] [mask-image:radial-gradient(ellipse_at_center,black_30%,transparent_75%)]"></div>
        <div class="relative flex h-full flex-col justify-between p-14 text-white">
            <div class="text-sm font-semibold uppercase tracking-[0.2em] text-brand-500">Finanças no prumo</div>
            <div>
                <blockquote class="font-display text-4xl font-semibold leading-tight">
                    Saiba quanto você pode gastar <span class="text-brand-500">hoje</span>, quando fica livre das dívidas e quanto falta para os seus sonhos.
                </blockquote>
                <ul class="mt-10 grid gap-4 text-[#c2c1bb]">
                    <li class="flex gap-3"><span class="text-xl">💑</span> Individual ou a dois, com acerto de contas justo</li>
                    <li class="flex gap-3"><span class="text-xl">⛓️</span> Plano de saída das dívidas com data de liberdade</li>
                    <li class="flex gap-3"><span class="text-xl">🧠</span> Consultor com IA que conhece seus números</li>
                    <li class="flex gap-3"><span class="text-xl">📈</span> Investimentos com cotações e CDI/IPCA oficiais</li>
                </ul>
            </div>
            <div class="text-xs text-[#a1a09a]">Seus dados ficam no seu servidor. Sem anúncios, sem venda de dados.</div>
        </div>
    </div>
</div>
</body>
</html>
