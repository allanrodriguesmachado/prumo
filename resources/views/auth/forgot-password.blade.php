<x-layouts.guest title="Recuperar senha">
    <h1 class="font-display text-3xl font-bold tracking-tight text-slate-900">Esqueceu a senha?</h1>
    <p class="mt-2 text-sm text-slate-500">Informe seu e-mail e enviaremos um link para criar uma nova.</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <div>
            <label class="label" for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input @error('email') input-error @enderror">
            <x-field-error name="email" />
        </div>
        <button class="btn-primary w-full py-3">Enviar link</button>
    </form>
    <p class="mt-8 text-center text-sm"><a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">← Voltar para o login</a></p>
</x-layouts.guest>
