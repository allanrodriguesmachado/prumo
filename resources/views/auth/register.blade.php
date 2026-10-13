<x-layouts.guest title="Criar conta">
    <h1 class="font-display text-3xl font-bold tracking-tight text-slate-900">Crie sua conta</h1>
    <p class="mt-2 text-sm text-slate-500">
        @if($invite) Você foi convidado(a) para uma carteira de casal. 💑 Crie sua conta para entrar.
        @else Leva menos de 2 minutos. Depois você escolhe: uso individual ou a dois. @endif
    </p>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="invite" value="{{ old('invite', $invite) }}">
        <div>
            <label class="label" for="name">Nome</label>
            <input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" class="input @error('name') input-error @enderror">
            <x-field-error name="name" />
        </div>
        <div>
            <label class="label" for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="input @error('email') input-error @enderror">
            <x-field-error name="email" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="password">Senha</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" class="input @error('password') input-error @enderror">
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirmar</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
            </div>
        </div>
        <p class="help -mt-3">Mínimo de 8 caracteres, com letras e números.</p>
        <x-field-error name="password" />
        <label class="flex items-start gap-2 text-sm text-slate-600">
            <input type="checkbox" name="terms" value="1" class="checkbox mt-0.5" @checked(old('terms'))>
            <span>Concordo que meus dados financeiros ficam armazenados neste servidor e entendo que o Prumo oferece educação financeira, não consultoria de investimentos.</span>
        </label>
        <x-field-error name="terms" />
        <button class="btn-primary w-full py-3">Criar conta</button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-500">
        Já tem conta? <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">Entrar</a>
    </p>
</x-layouts.guest>
