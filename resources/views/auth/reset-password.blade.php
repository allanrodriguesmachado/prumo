<x-layouts.guest title="Nova senha">
    <h1 class="font-display text-3xl font-bold tracking-tight text-slate-900">Crie uma nova senha</h1>
    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label class="label" for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required class="input @error('email') input-error @enderror">
            <x-field-error name="email" />
        </div>
        <div>
            <label class="label" for="password">Nova senha</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="input @error('password') input-error @enderror">
            <x-field-error name="password" />
        </div>
        <div>
            <label class="label" for="password_confirmation">Confirmar senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required class="input">
        </div>
        <button class="btn-primary w-full py-3">Salvar nova senha</button>
    </form>
</x-layouts.guest>
