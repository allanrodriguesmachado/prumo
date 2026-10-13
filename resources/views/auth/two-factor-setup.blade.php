<x-layouts.app title="Ativar 2FA">
    <div class="mx-auto max-w-lg">
        <x-page-header title="Verificação em duas etapas" subtitle="Mesmo que alguém descubra sua senha, não entra sem o código do seu celular." />
        <div class="card card-pad space-y-5">
            <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-600">
                <li>Abra um app autenticador (Google Authenticator, Microsoft Authenticator, Authy, 1Password...).</li>
                <li>Escaneie o QR code abaixo ou digite a chave manualmente.</li>
                <li>Informe o código de 6 dígitos que aparecer no app.</li>
            </ol>
            <div class="flex justify-center rounded-2xl bg-white p-4 ring-1 ring-slate-200 [&_svg]:h-48 [&_svg]:w-48" aria-label="QR code para o app autenticador">{!! $qr !!}</div>
            <div x-data="{ copied: false }" class="text-center">
                <div class="text-xs text-slate-500">Chave manual</div>
                <button type="button" class="mt-1 font-mono text-sm tracking-wider text-slate-800 hover:underline" @click="navigator.clipboard.writeText('{{ $secret }}'); copied = true; setTimeout(() => copied = false, 1500)">{{ trim(chunk_split($secret, 4, ' ')) }}</button>
                <div x-show="copied" x-cloak class="text-xs text-emerald-600">copiada!</div>
            </div>
            <form method="POST" action="{{ route('two-factor.enable') }}" class="space-y-3">
                @csrf
                <label class="label" for="code">Código de 6 dígitos</label>
                <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus class="input text-center text-2xl tracking-[0.5em] @error('code') input-error @enderror">
                <x-field-error name="code" />
                <div class="flex justify-between">
                    <a href="{{ route('profile.edit') }}" class="btn-ghost">Cancelar</a>
                    <button class="btn-primary">Ativar</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
