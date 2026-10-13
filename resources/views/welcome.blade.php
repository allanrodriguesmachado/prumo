<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prumo — finanças no prumo, sozinho ou a dois</title>
    <meta name="description" content="Gestão financeira completa: renda, gastos fixos, parcelamentos, plano para sair das dívidas, investimentos e consultor com IA.">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='9' fill='%23F53003'/><path d='M16 6v13' stroke='white' stroke-width='3' stroke-linecap='round'/><path d='M9.5 20.5L16 27l6.5-6.5z' fill='white'/><path d='M10 9h12' stroke='white' stroke-width='2.4' stroke-linecap='round'/></svg>">
    <script>try{var t=localStorage.getItem('prumo-theme')||'system';if(t==='dark'||(t==='system'&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')}catch(e){}</script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#fdfdfc] font-sans text-slate-800 antialiased bg-[radial-gradient(60%_50%_at_50%_0%,rgba(245,48,3,.08),transparent_70%)] bg-no-repeat">
<header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
    <div class="flex items-center gap-2.5"><x-logo class="h-9 w-9" /><span class="font-display text-2xl font-semibold tracking-tight text-slate-900">Prumo</span></div>
    <nav class="flex items-center gap-2">
        <a href="{{ route('login') }}" class="btn-ghost">Entrar</a>
        <a href="{{ route('register') }}" class="btn-primary">Criar conta</a>
    </nav>
</header>

<section class="mx-auto max-w-6xl px-6 pb-20 pt-10 text-center sm:pt-16">
    <span class="badge-brand px-3 py-1 text-xs">Grátis · código aberto · seus dados no seu servidor</span>
    <h1 class="mx-auto mt-6 max-w-4xl font-display text-4xl font-extrabold leading-[1.05] tracking-tight text-slate-900 sm:text-6xl">
        Coloque sua vida financeira <span class="bg-gradient-to-r from-brand-600 to-brand-500 bg-clip-text text-transparent">no prumo</span> — sozinho ou a dois.
    </h1>
    <p class="mx-auto mt-6 max-w-2xl text-lg text-slate-600">Não é só mais um app de planilha. O Prumo diz <b>quanto você pode gastar hoje</b>, traça a <b>data em que você fica livre das dívidas</b> e tem um <b>consultor com IA</b> que conhece seus números de verdade.</p>
    <div class="mt-10 flex flex-wrap justify-center gap-3">
        <a href="{{ route('register') }}" class="btn-primary px-6 py-3.5 text-base">Começar agora</a>
        <a href="#diferenciais" class="btn-secondary px-6 py-3.5 text-base">Ver diferenciais</a>
    </div>
</section>

<section id="diferenciais" class="bg-slate-50 py-20">
    <div class="mx-auto max-w-6xl px-6">
        <h2 class="text-center font-display text-3xl font-bold tracking-tight text-slate-900">O que o Prumo faz que os outros não fazem</h2>
        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                ['📅', 'Quanto posso gastar hoje', 'Desconta contas fixas, parcelas, faturas e sua meta de poupança e divide pelo que falta do mês.'],
                ['⛓️', 'Plano de saída das dívidas', 'Simula avalanche, bola de neve e o método Prumo, mostra a data de liberdade, os juros economizados e um roteiro de negociação.'],
                ['💑', 'Modo casal com acerto justo', 'Divisão 50/50, proporcional à renda ou combinada. O Prumo calcula quem transfere quanto para quem no fim do mês.'],
                ['⏱️', 'Custo em horas de vida', 'Cada gasto mostra quantas horas do seu trabalho ele custou. Muda a forma como você compra.'],
                ['🧠', 'Consultor com IA', 'Funciona com IA gratuita (Gemini, Groq ou Ollama local) e tem modo offline com regras financeiras.'],
                ['🎯', 'Prumo Score', 'Nota de 0 a 1000 da sua saúde financeira em 6 pilares, com o que fazer para subir.'],
                ['📡', 'Radar de parcelas', 'Veja quando cada parcelamento termina e quanto da renda vai ser liberado em cada mês.'],
                ['📈', 'Investimentos de verdade', 'Ações, FIIs, ETFs, cripto e renda fixa marcada com CDI/IPCA oficiais do Banco Central.'],
                ['🤖', 'Lance pelo Telegram', 'Mande "32,50 almoço" para o bot e pronto. Categoria sugerida automaticamente.'],
            ] as [$i, $t, $d])
                <div class="card card-pad">
                    <div class="text-3xl">{{ $i }}</div>
                    <h3 class="mt-3 font-display text-lg font-semibold text-slate-900">{{ $t }}</h3>
                    <p class="mt-1.5 text-sm text-slate-600">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<footer class="py-10 text-center text-sm text-slate-500">Prumo · educação financeira, não consultoria de investimentos.</footer>
</body>
</html>
