<x-layouts.guest title="Começar">
    <h1 class="font-display text-3xl font-bold tracking-tight text-slate-900">Como você vai usar o Prumo?</h1>
    <p class="mt-2 text-sm text-slate-500">Você pode criar outras carteiras depois (ex.: uma individual e uma do casal).</p>

    <form method="POST" action="{{ route('onboarding.store') }}" class="mt-8 space-y-4" x-data="{ type: 'individual' }">
        @csrf
        <label class="flex cursor-pointer gap-4 rounded-2xl border-2 p-4 transition" :class="type === 'individual' ? 'border-brand-600 bg-brand-50' : 'border-slate-200 hover:border-slate-300'">
            <input type="radio" name="type" value="individual" x-model="type" class="sr-only">
            <span class="text-3xl">🙂</span>
            <span>
                <span class="block font-semibold text-slate-900">Só eu</span>
                <span class="block text-sm text-slate-500">Organize sua renda, gastos, dívidas e investimentos.</span>
            </span>
        </label>
        <label class="flex cursor-pointer gap-4 rounded-2xl border-2 p-4 transition" :class="type === 'couple' ? 'border-brand-600 bg-brand-50' : 'border-slate-200 hover:border-slate-300'">
            <input type="radio" name="type" value="couple" x-model="type" class="sr-only">
            <span class="text-3xl">💑</span>
            <span>
                <span class="block font-semibold text-slate-900">Eu e meu amor</span>
                <span class="block text-sm text-slate-500">Cada um lança seus gastos; o Prumo divide as contas da casa de forma justa e mostra quem deve acertar quanto.</span>
            </span>
        </label>
        <div>
            <label class="label" for="name">Nome da carteira <span class="font-normal text-slate-400">(opcional)</span></label>
            <input id="name" name="name" class="input" :placeholder="type === 'couple' ? 'Ex.: Casa Ana & Léo' : 'Ex.: Minhas finanças'">
        </div>
        <button class="btn-primary w-full py-3">Continuar</button>
    </form>

    <div class="mt-10 rounded-2xl border border-dashed border-slate-300 p-5">
        <div class="text-sm font-semibold text-slate-800">Recebeu um convite do seu par?</div>
        <form method="POST" action="{{ route('onboarding.join') }}" class="mt-3 flex gap-2">
            @csrf
            <input name="code" value="{{ old('code', $invite) }}" placeholder="Código do convite" class="input uppercase tracking-widest" maxlength="16">
            <button class="btn-secondary shrink-0">Entrar</button>
        </form>
        <x-field-error name="code" />
    </div>
</x-layouts.guest>
