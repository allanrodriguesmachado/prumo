<x-layouts.guest title="Entrar">
    <h1 class="font-display text-3xl font-bold tracking-tight text-slate-900">Bem-vindo de volta</h1>
    <p class="mt-2 text-sm text-slate-500">Entre para ver como estão suas finanças hoje.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf
        <div>
            <label class="label" for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="input @error('email') input-error @enderror">
            <x-field-error name="email" />
        </div>
        <div x-data="{ show: false }">
            <div class="flex items-center justify-between">
                <label class="label" for="password">Senha</label>
                <a href="{{ route('password.request') }}" class="mb-1.5 text-xs font-semibold text-brand-700 hover:underline">Esqueci a senha</a>
            </div>
            <div class="relative">
                <input id="password" name="password" :type="show ? 'text' : 'password'" required autocomplete="current-password" class="input pr-16">
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-slate-500" x-text="show ? 'ocultar' : 'mostrar'"></button>
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="checkbox"> Manter conectado
        </label>
        <button class="btn-primary w-full py-3">Entrar</button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-500">
        Ainda não tem conta? <a href="{{ route('register') }}" class="font-semibold text-brand-700 hover:underline">Criar conta grátis</a>
    </p>
</x-layouts.guest>
