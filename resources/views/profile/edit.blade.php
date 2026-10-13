@php
    $aiNames = ['gemini' => 'Google Gemini', 'groq' => 'Groq', 'anthropic' => 'Anthropic Claude', 'ollama' => 'Ollama (local)'];
    $botUser = ltrim((string) $telegramBot, '@');
@endphp

<x-layouts.app title="Meu perfil">
    <x-page-header title="Meu perfil" subtitle="Seus dados, notificações e integrações." />

    <div class="mx-auto max-w-4xl space-y-6">
        {{-- Perfil + notificações --}}
        <form method="POST" action="{{ route('profile.update') }}" class="card card-pad space-y-6">
            @csrf @method('PUT')
            <div>
                <h3 class="card-title">👤 Perfil</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="f-name">Nome</label>
                        <input id="f-name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" class="input {{ $errors->has('name') ? 'input-error' : '' }}">
                        <x-field-error name="name" />
                    </div>
                    <div>
                        <label class="label" for="f-email">E-mail</label>
                        <input id="f-email" type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="190" class="input {{ $errors->has('email') ? 'input-error' : '' }}">
                        <x-field-error name="email" />
                    </div>
                    <div class="sm:col-span-2 sm:max-w-xs">
                        <label class="label" for="f-hours">Horas de trabalho por mês</label>
                        <input id="f-hours" type="number" name="work_hours_per_month" min="20" max="400" step="1" required
                               value="{{ old('work_hours_per_month', $user->work_hours_per_month ?? 176) }}" class="input tabular {{ $errors->has('work_hours_per_month') ? 'input-error' : '' }}">
                        <p class="help">Usado para mostrar quantas horas de trabalho cada gasto custa. Ex.: 44h semanais ≈ 176h/mês.</p>
                        <x-field-error name="work_hours_per_month" />
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-6">
                <h3 class="card-title">🔔 Notificações</h3>
                <p class="muted mt-1 text-xs">Avisos de contas a vencer, orçamento estourando e resumos do mês.</p>
                <div class="mt-4 space-y-3">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="notify_email" value="1" class="checkbox mt-0.5" @checked(old('notify_email', $user->notify_email))>
                        <span><span class="block text-sm font-medium text-slate-800">Por e-mail</span><span class="block text-xs text-slate-500">Enviados para {{ $user->email }}.</span></span>
                    </label>
                    <label class="flex items-start gap-3 {{ ! $user->telegram_chat_id ? 'opacity-60' : '' }}">
                        <input type="checkbox" name="notify_telegram" value="1" class="checkbox mt-0.5" @checked(old('notify_telegram', $user->notify_telegram))>
                        <span><span class="block text-sm font-medium text-slate-800">Pelo Telegram</span>
                            <span class="block text-xs text-slate-500">{{ $user->telegram_chat_id ? 'Mensagens direto no seu Telegram.' : 'Conecte o Telegram abaixo para receber por lá.' }}</span></span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end"><button class="btn-primary">Salvar perfil</button></div>
        </form>

        {{-- Telegram --}}
        <div class="card card-pad" id="telegram">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="card-title">✈️ Telegram</h3>
                    <p class="muted mt-1 text-xs">Lance gastos em segundos mandando uma mensagem para o bot — e receba seus avisos por lá.</p>
                </div>
                @if($telegramEnabled && $user->telegram_chat_id)
                    <span class="badge-green whitespace-nowrap">● Conectado</span>
                @elseif(! $telegramEnabled)
                    <span class="badge-slate whitespace-nowrap">Não configurado</span>
                @endif
            </div>

            @if(! $telegramEnabled)
                <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                    <p class="font-medium">O bot do Telegram ainda não foi configurado neste servidor. É gratuito:</p>
                    <ol class="mt-2 list-decimal space-y-1 pl-5">
                        <li>No Telegram, abra o <a href="https://t.me/BotFather" target="_blank" rel="noopener" class="font-medium text-brand-700 underline">@BotFather</a> e envie <code class="rounded bg-white px-1">/newbot</code>.</li>
                        <li>Escolha um nome e um usuário para o bot. Copie o <strong>token</strong> que ele mostrar.</li>
                        <li>No arquivo <code class="rounded bg-white px-1">.env</code> do servidor, preencha:
                            <pre class="mt-1 overflow-x-auto rounded-lg bg-slate-900 px-3 py-2 text-xs text-slate-100">TELEGRAM_BOT_TOKEN=123456:ABC...
TELEGRAM_BOT_USERNAME=meu_prumo_bot</pre>
                        </li>
                        <li>Reinicie a aplicação e volte a esta página.</li>
                    </ol>
                </div>
            @elseif($user->telegram_chat_id)
                <p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    ✅ Seu Telegram está conectado{{ $botUser ? ' ao @'.$botUser : '' }}. É só mandar mensagens para o bot.
                </p>
            @else
                <div class="mt-4 space-y-3">
                    @if($user->telegram_link_code)
                        <div class="rounded-xl bg-brand-50 p-4 ring-1 ring-brand-200" x-data="{ copied: false }">
                            <p class="text-sm text-brand-900">Envie esta mensagem para <strong>{{ '@'.$botUser }}</strong>:</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <code class="rounded-lg bg-white px-3 py-2 font-mono text-base font-bold text-slate-900 ring-1 ring-slate-200">/vincular {{ $user->telegram_link_code }}</code>
                                <button type="button" class="btn-secondary btn-sm"
                                        @click="navigator.clipboard.writeText(@js('/vincular '.$user->telegram_link_code)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                        x-text="copied ? '✓ Copiado' : 'Copiar'">Copiar</button>
                                @if($botUser)
                                    <a href="https://t.me/{{ $botUser }}?start={{ $user->telegram_link_code }}" target="_blank" rel="noopener" class="btn-primary btn-sm">Abrir no Telegram</a>
                                @endif
                            </div>
                            <p class="mt-2 text-xs text-brand-800/70">Depois de enviar, recarregue esta página.</p>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('profile.telegram') }}">
                        @csrf
                        <button class="{{ $user->telegram_link_code ? 'btn-secondary' : 'btn-primary' }}">{{ $user->telegram_link_code ? 'Gerar novo código' : 'Conectar meu Telegram' }}</button>
                    </form>
                </div>
            @endif

            <div class="mt-5 border-t border-slate-100 pt-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">O que você pode mandar para o bot</div>
                <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                    @foreach([
                        ['32,50 almoço', 'Lança um gasto de R$ 32,50'],
                        ['+150 freela', 'Lança uma entrada de R$ 150'],
                        ['/hoje', 'Quanto você pode gastar hoje'],
                        ['/saldo', 'Saldo das suas contas'],
                        ['/resumo', 'Resumo do mês'],
                    ] as [$cmd, $desc])
                        <div class="flex items-center gap-2">
                            <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-800">{{ $cmd }}</code>
                            <span class="text-xs text-slate-500">{{ $desc }}</span>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>

        {{-- Integrações --}}
        <div class="card card-pad" id="integracoes">
            <h3 class="card-title">🔌 Integrações</h3>
            <p class="muted mt-1 text-xs">Tudo gratuito. As chaves ficam no arquivo <code>.env</code> do seu servidor.</p>

            <ul class="mt-4 divide-y divide-slate-100">
                <li class="flex items-start justify-between gap-3 py-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-800">🤖 Consultor com IA</div>
                        <div class="text-xs text-slate-500">
                            @if(empty($providers))
                                Sem IA externa — o consultor funciona no modo offline, com análises por regras.
                            @else
                                Ativo: {{ implode(', ', array_map(fn ($p) => $aiNames[$p] ?? $p, $providers)) }}
                            @endif
                        </div>
                    </div>
                    @if(empty($providers))<span class="badge-amber whitespace-nowrap">Offline</span>@else<span class="badge-green whitespace-nowrap">Ativo</span>@endif
                </li>
                <li class="flex items-start justify-between gap-3 py-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-800">📈 Cotações da B3 (Brapi)</div>
                        <div class="text-xs text-slate-500">{{ $brapi ? 'Token configurado: cotações de ações, FIIs e ETFs.' : 'Sem token: algumas cotações podem ficar limitadas.' }}</div>
                    </div>
                    @if($brapi)<span class="badge-green whitespace-nowrap">Ativo</span>@else<span class="badge-slate whitespace-nowrap">Opcional</span>@endif
                </li>
                @foreach([
                    ['🏛️ Banco Central (SGS)', 'CDI, Selic e IPCA oficiais.'],
                    ['🇧🇷 BrasilAPI', 'Feriados nacionais e dados de bancos.'],
                    ['🪙 CoinGecko', 'Cotações de criptomoedas.'],
                    ['💱 AwesomeAPI', 'Cotações de dólar, euro e outras moedas.'],
                ] as [$name, $desc])
                    <li class="flex items-start justify-between gap-3 py-3">
                        <div>
                            <div class="text-sm font-semibold text-slate-800">{{ $name }}</div>
                            <div class="text-xs text-slate-500">{{ $desc }} Não precisa de chave.</div>
                        </div>
                        <span class="badge-green whitespace-nowrap">Sempre ativo</span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4 rounded-xl bg-slate-50 p-4" x-data="{ tab: 'gemini' }">
                <div class="text-sm font-semibold text-slate-800">Como ativar a IA de graça</div>
                <p class="mt-1 text-xs text-slate-500">Escolha uma opção (ou várias — o Prumo usa a primeira disponível e cai para a próxima se uma falhar).</p>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach(['gemini' => 'Gemini', 'groq' => 'Groq', 'ollama' => 'Ollama (local)', 'brapi' => 'Brapi'] as $k => $l)
                        <button type="button" @click="tab = '{{ $k }}'" :class="tab === '{{ $k }}' ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100'"
                                class="rounded-full px-3 py-1 text-xs font-medium transition">{{ $l }}</button>
                    @endforeach
                </div>
                <div class="mt-3 text-sm text-slate-700">
                    <div x-show="tab === 'gemini'">
                        <ol class="list-decimal space-y-1 pl-5">
                            <li>Acesse <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener" class="font-medium text-brand-700 underline">aistudio.google.com/apikey</a> com sua conta Google.</li>
                            <li>Clique em “Create API key” e copie a chave.</li>
                            <li>No <code>.env</code>: <code class="rounded bg-white px-1">GEMINI_API_KEY=sua_chave</code></li>
                        </ol>
                    </div>
                    <div x-cloak x-show="tab === 'groq'">
                        <ol class="list-decimal space-y-1 pl-5">
                            <li>Crie uma conta em <a href="https://console.groq.com/keys" target="_blank" rel="noopener" class="font-medium text-brand-700 underline">console.groq.com/keys</a>.</li>
                            <li>Gere uma nova chave e copie.</li>
                            <li>No <code>.env</code>: <code class="rounded bg-white px-1">GROQ_API_KEY=sua_chave</code></li>
                        </ol>
                    </div>
                    <div x-cloak x-show="tab === 'ollama'">
                        <p>Roda a IA no seu próprio servidor — nada sai da sua máquina. Precisa de uns 4 GB de RAM livres.</p>
                        <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 px-3 py-2 text-xs text-slate-100">docker compose --profile ai up -d
docker compose exec ollama ollama pull qwen2.5:3b</pre>
                    </div>
                    <div x-cloak x-show="tab === 'brapi'">
                        <ol class="list-decimal space-y-1 pl-5">
                            <li>Cadastre-se grátis em <a href="https://brapi.dev" target="_blank" rel="noopener" class="font-medium text-brand-700 underline">brapi.dev</a> e copie seu token.</li>
                            <li>No <code>.env</code>: <code class="rounded bg-white px-1">BRAPI_TOKEN=seu_token</code></li>
                        </ol>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-slate-400">Depois de editar o <code>.env</code>, reinicie a aplicação.</p>
            </div>
        </div>

        {{-- Senha --}}
        <form method="POST" action="{{ route('profile.password') }}" class="card card-pad">
            @csrf @method('PUT')
            <h3 class="card-title">🔒 Senha</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="label" for="f-current">Senha atual</label>
                    <input id="f-current" type="password" name="current_password" required autocomplete="current-password" class="input {{ $errors->has('current_password') ? 'input-error' : '' }}">
                    <x-field-error name="current_password" />
                </div>
                <div>
                    <label class="label" for="f-new">Nova senha</label>
                    <input id="f-new" type="password" name="password" required autocomplete="new-password" class="input {{ $errors->has('password') ? 'input-error' : '' }}">
                    <p class="help">Mínimo 8 caracteres, com letras e números.</p>
                </div>
                <div>
                    <label class="label" for="f-confirm">Confirme a nova senha</label>
                    <input id="f-confirm" type="password" name="password_confirmation" required autocomplete="new-password" class="input">
                </div>
            </div>
            <div class="mt-4 flex justify-end"><button class="btn-primary">Alterar senha</button></div>
        </form>

        {{-- Segurança: 2FA, privacidade e auditoria --}}
        <div class="card card-pad" id="seguranca">
            <h3 class="card-title">🛡️ Segurança e privacidade</h3>

            @if(session('recovery_codes'))
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div class="text-sm font-semibold text-amber-900">Guarde estes códigos de recuperação em local seguro</div>
                    <p class="text-xs text-amber-800">Cada código funciona uma única vez, caso você perca o celular. Eles não serão mostrados de novo.</p>
                    <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">
                        @foreach(session('recovery_codes') as $code)<span class="rounded-lg bg-white px-2 py-1 text-center ring-1 ring-amber-200">{{ $code }}</span>@endforeach
                    </div>
                </div>
            @endif

            <div class="mt-4 flex flex-col gap-3 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-sm font-semibold text-slate-800">Verificação em duas etapas (2FA)</div>
                    <p class="text-xs text-slate-500">
                        @if($user->hasTwoFactor())
                            <span class="badge-green">Ativa</span> desde {{ $user->two_factor_confirmed_at->format('d/m/Y') }} · {{ count($user->two_factor_recovery_codes ?? []) }} códigos de recuperação restantes
                        @else
                            Proteja sua conta com um código do app autenticador (Google Authenticator, Authy, 1Password...).
                        @endif
                    </p>
                </div>
                @if($user->hasTwoFactor())
                    <form method="POST" action="{{ route('two-factor.disable') }}" class="flex flex-wrap items-center gap-2" x-data>
                        @csrf @method('DELETE')
                        <input type="password" name="password" required placeholder="Sua senha" autocomplete="current-password" class="input w-40 py-2">
                        <button class="btn-secondary btn-sm" formaction="{{ route('two-factor.regenerate') }}" formmethod="POST" onclick="this.form.querySelector('input[name=_method]').disabled = true">Novos códigos</button>
                        <button class="btn-danger btn-sm" onclick="return confirm('Desativar a verificação em duas etapas?')">Desativar</button>
                    </form>
                @else
                    <a href="{{ route('two-factor.setup') }}" class="btn-primary btn-sm">Ativar 2FA</a>
                @endif
            </div>
            @error('password', 'twoFactor')<p class="error">{{ $message }}</p>@enderror

            <div class="mt-4 flex flex-col gap-3 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-sm font-semibold text-slate-800">Seus dados (LGPD)</div>
                    <p class="text-xs text-slate-500">Baixe uma cópia completa dos seus dados em JSON. Observações de lançamentos, conversas com a IA e segredos de 2FA ficam criptografados no banco.</p>
                </div>
                <a href="{{ route('profile.export') }}" class="btn-secondary btn-sm">⬇️ Exportar meus dados</a>
            </div>

            <div class="mt-5">
                <div class="text-sm font-semibold text-slate-800">Atividade recente da conta</div>
                <ul class="mt-2 divide-y divide-slate-100 text-sm">
                    @php
                        $events = ['login' => '🔓 Login', 'logout' => '🚪 Saída', 'login_failed' => '⚠️ Tentativa de login falhou', '2fa_enabled' => '🛡️ 2FA ativado', '2fa_disabled' => '🛡️ 2FA desativado', '2fa_failed' => '⚠️ Código 2FA inválido', 'login_recovery_code' => '🔑 Login com código de recuperação', 'password_changed' => '🔒 Senha alterada', 'data_exported' => '⬇️ Dados exportados', '2fa_recovery_regenerated' => '🔑 Novos códigos de recuperação', 'created' => '➕ Criou', 'updated' => '✏️ Alterou', 'deleted' => '🗑️ Excluiu'];
                        $types = ['Transaction' => 'lançamento', 'Account' => 'conta', 'CreditCard' => 'cartão', 'Recurrence' => 'recorrência', 'Debt' => 'dívida', 'Goal' => 'meta', 'Asset' => 'investimento'];
                    @endphp
                    @forelse($audit as $log)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span>{{ $events[$log->event] ?? $log->event }} {{ $log->auditable_type && isset($types[$log->auditable_type]) && in_array($log->event, ['created', 'updated', 'deleted']) ? $types[$log->auditable_type].' #'.$log->auditable_id : '' }}</span>
                            <span class="shrink-0 text-xs text-slate-400">{{ $log->created_at->format('d/m H:i') }} · {{ $log->ip_address ?? '—' }}</span>
                        </li>
                    @empty
                        <li class="py-3 text-slate-500">Nenhuma atividade registrada ainda.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- Zona de perigo --}}
        <div class="card card-pad border border-rose-200">
            <h3 class="card-title text-rose-700">⚠️ Zona de perigo</h3>
            <p class="mt-1 text-sm text-slate-600">
                Excluir sua conta apaga seus dados para sempre. Carteiras de casal continuam com seu parceiro(a), que fica com o histórico compartilhado.
            </p>
            <div class="mt-4">
                <button type="button" class="btn-danger" @click="$dispatch('open-modal', 'delete-account')">Excluir minha conta</button>
            </div>
        </div>
    </div>

    <x-modal name="delete-account" title="Excluir conta definitivamente?">
        <form method="POST" action="{{ route('profile.destroy') }}" class="space-y-4">
            @csrf @method('DELETE')
            <p class="text-sm text-slate-600">Essa ação não pode ser desfeita. Para confirmar, digite sua senha.</p>
            <div>
                <label class="label" for="f-del-pass">Senha</label>
                <input id="f-del-pass" type="password" name="password" required autocomplete="current-password" class="input">
                @error('password', 'deleteAccount')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" @click="open = false">Cancelar</button>
                <button class="btn-danger">Excluir para sempre</button>
            </div>
        </form>
    </x-modal>
</x-layouts.app>
