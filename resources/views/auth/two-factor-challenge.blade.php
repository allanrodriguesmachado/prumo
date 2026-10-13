<x-layouts.guest title="Verificação">
    <div x-data="{ recovery: false }">
        <h1 class="font-display text-3xl font-bold tracking-tight text-slate-900">Verificação em duas etapas</h1>
        <p class="mt-2 text-sm text-slate-500" x-show="!recovery">Digite o código de 6 dígitos do seu app autenticador.</p>
        <p class="mt-2 text-sm text-slate-500" x-show="recovery" x-cloak>Digite um dos seus códigos de recuperação.</p>
        <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-8 space-y-5">
            @csrf
            <div x-show="!recovery">
                <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus x-bind:disabled="recovery" class="input text-center text-2xl tracking-[0.5em] @error('code') input-error @enderror" aria-label="Código">
            </div>
            <div x-show="recovery" x-cloak>
                <input name="recovery_code" x-bind:disabled="!recovery" class="input text-center font-mono uppercase tracking-widest" placeholder="XXXXX-XXXXX" aria-label="Código de recuperação">
            </div>
            <x-field-error name="code" />
            <button class="btn-primary w-full py-3">Verificar</button>
        </form>
        <button type="button" class="mt-6 w-full text-center text-sm font-semibold text-brand-700 hover:underline" @click="recovery = !recovery" x-text="recovery ? 'Usar código do app' : 'Perdi o celular — usar código de recuperação'"></button>
        <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="text-slate-500 hover:underline">← Voltar</a></p>
    </div>
</x-layouts.guest>
