@php
    $user = auth()->user();
    $isCouple = $household->isCouple();
    $owner = $household->members->firstWhere('id', $household->owner_id) ?? $household->owner;
    $partner = $household->members->firstWhere('id', '!=', $household->owner_id);
    $waText = "Oi! Vamos organizar nossas finanças juntos no Prumo? 💚 Entre na nossa carteira por este link: {$inviteUrl} (código: {$household->invite_code})";
    $waUrl = 'https://wa.me/?text='.rawurlencode($waText);
    $modes = [
        'equal' => ['⚖️', '50/50', 'Cada um paga metade das despesas compartilhadas.'],
        'proportional' => ['📊', 'Proporcional à renda (recomendado)', 'Quem ganha mais contribui com uma parte maior. Justo quando as rendas são diferentes.'],
        'custom' => ['🤝', 'Percentual combinado', 'Vocês escolhem a divisão que faz sentido para os dois.'],
    ];
@endphp

<x-layouts.app :title="$isCouple ? 'Casal' : 'Carteira & casal'">
    <x-page-header :title="$isCouple ? 'Finanças a dois' : 'Carteira & casal'"
                   :subtitle="$isCouple ? 'Despesas compartilhadas, divisão justa e acerto do mês — sem planilha e sem briga.' : 'Organize o dinheiro sozinho ou convide quem divide a vida com você.'">
        @if($isCouple && $settlement)
            <x-slot:actions><x-month-nav :month="$month" route="couple.index" /></x-slot:actions>
        @endif
    </x-page-header>

    @if(! $isCouple || ! $settlement)
        {{-- Convite --}}
        <div class="relative overflow-hidden rounded-3xl hero p-6 text-white shadow-lg sm:p-10"
             x-data="{ copied: null, copy(text, key) { navigator.clipboard.writeText(text).then(() => { this.copied = key; setTimeout(() => this.copied = null, 2000) }) } }">
            <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-brand-400/10"></div>
            <div class="relative max-w-2xl">
                <div class="text-4xl">💑</div>
                <h3 class="mt-3 font-display text-2xl font-bold sm:text-3xl">
                    {{ $isCouple ? 'Falta só o seu par entrar!' : 'Dinheiro a dois fica mais leve' }}
                </h3>
                <p class="mt-2 text-brand-100/90">
                    Convide seu parceiro(a) para a carteira <strong>{{ $household->name }}</strong>. Cada um lança os próprios gastos,
                    vocês marcam o que é do casal e o Prumo calcula quem deve quanto para quem no fim do mês.
                    @unless($isCouple) Ao entrar, a carteira vira uma carteira de casal. @endunless
                </p>

                <div class="mt-6 space-y-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-brand-300">Link de convite</div>
                        <div class="mt-1 flex flex-col gap-2 sm:flex-row">
                            <input type="text" readonly value="{{ $inviteUrl }}" class="input flex-1 border-white/20 bg-white/10 text-white" @focus="$event.target.select()">
                            <button type="button" @click="copy(@js($inviteUrl), 'link')" class="btn-secondary whitespace-nowrap">
                                <span x-text="copied === 'link' ? '✓ Copiado!' : 'Copiar link'">Copiar link</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-brand-300">Ou use o código</div>
                            <button type="button" @click="copy(@js($household->invite_code), 'code')" class="mt-1 rounded-xl bg-white/10 px-4 py-2 font-mono text-xl font-bold tracking-[0.25em] ring-1 ring-white/20 hover:bg-white/15" title="Copiar código">
                                {{ $household->invite_code }}
                            </button>
                            <span x-cloak x-show="copied === 'code'" class="ml-2 text-xs text-brand-200">✓ Copiado!</span>
                        </div>
                        <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-[#25D366] px-4 py-2.5 text-sm font-semibold text-white shadow hover:brightness-95">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm0 18.2a8.2 8.2 0 01-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1112 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 01-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 00-.7.3 3 3 0 00-.9 2.2 5.1 5.1 0 001.1 2.7 11.7 11.7 0 004.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 001.8-1.2 2.2 2.2 0 00.1-1.3c0-.1-.2-.2-.5-.3z"/></svg>
                            Enviar pelo WhatsApp
                        </a>
                    </div>
                </div>
                <p class="mt-5 text-xs text-brand-200/80">Quem receber cria a conta (ou entra) e é adicionado automaticamente. Se o código vazar, gere um novo nas configurações abaixo.</p>
            </div>
        </div>
    @else
        {{-- Membros --}}
        <div class="grid gap-4 md:grid-cols-2">
            @foreach($settlement['members'] as $uid => $m)
                <div class="card card-pad">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 place-items-center rounded-full bg-brand-100 font-bold text-brand-800">{{ $m['user']->initials() }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold text-slate-900">{{ $m['user']->name }} @if($uid === $user->id)<span class="text-xs font-normal text-slate-400">(você)</span>@endif</div>
                            <div class="text-xs text-slate-500">Responsável por <strong>{{ pct($m['share_pct'], 0) }}</strong> das despesas do casal</div>
                        </div>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl bg-slate-50 px-3 py-2"><dt class="text-xs text-slate-500">Renda no mês</dt><dd class="font-semibold tabular money-pos">{{ brl($m['income']) }}</dd></div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2"><dt class="text-xs text-slate-500">Gastos pessoais</dt><dd class="font-semibold tabular text-slate-700">{{ brl($m['personal']) }}</dd></div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2"><dt class="text-xs text-slate-500">Pagou do casal</dt><dd class="font-semibold tabular text-slate-900">{{ brl($m['paid']) }}</dd></div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2"><dt class="text-xs text-slate-500">Parte justa</dt><dd class="font-semibold tabular text-slate-900">{{ brl($m['fair']) }}</dd></div>
                    </dl>
                    <div class="mt-3 text-sm {{ abs($m['diff']) < 0.01 ? 'text-slate-500' : ($m['diff'] > 0 ? 'text-emerald-700' : 'text-amber-700') }}">
                        @if(abs($m['diff']) < 0.01)
                            ✓ Pagou exatamente a sua parte.
                        @elseif($m['diff'] > 0)
                            ▲ Pagou {{ brl($m['diff']) }} a mais que a sua parte.
                        @else
                            ▼ Pagou {{ brl(abs($m['diff'])) }} a menos que a sua parte.
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Acerto --}}
        <div class="mt-6 overflow-hidden rounded-3xl hero p-6 text-white shadow-lg sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-brand-200">Acerto do mês · <span class="capitalize">{{ month_name($month) }}</span></div>
                    <div class="mt-1 text-sm text-brand-100/80">Total compartilhado: <strong class="tabular text-white">{{ brl($settlement['total_shared']) }}</strong> · divisão {{ strip_tags($modes[$settlement['mode']][1] ?? $settlement['mode']) }}</div>
                    @if($settled)
                        <div class="mt-3 font-display text-2xl font-bold">✅ Acertado em @php $at = \Carbon\Carbon::parse($settled['at']); @endphp{{ $at->format('d/m/Y') }} às {{ $at->format('H:i') }}</div>
                        <p class="mt-1 text-brand-100/90">{{ $settled['from'] }} transferiu <strong class="tabular">{{ brl($settled['amount']) }}</strong> para {{ $settled['to'] }}. Contas em dia! 💚</p>
                    @elseif($settlement['transfer'])
                        @php $t = $settlement['transfer']; @endphp
                        <div class="mt-3 font-display text-2xl font-bold leading-snug sm:text-3xl">
                            {{ $t['from']->firstName() }} transfere <span class="tabular text-brand-200">{{ brl($t['amount']) }}</span> para {{ $t['to']->firstName() }}
                        </div>
                        <p class="mt-1 text-sm text-brand-100/80">Assim cada um fica exatamente com a sua parte das despesas do casal.</p>
                    @else
                        <div class="mt-3 font-display text-2xl font-bold">🎉 Tudo equilibrado!</div>
                        <p class="mt-1 text-sm text-brand-100/80">{{ $settlement['total_shared'] > 0 ? 'Cada um já pagou a sua parte. Nada a acertar.' : 'Ainda não há despesas compartilhadas neste mês.' }}</p>
                    @endif
                </div>
                @if(! $settled && $settlement['transfer'])
                    <form method="POST" action="{{ route('couple.settle') }}" onsubmit="return confirm('Registrar que a transferência foi feita?')">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                        <button class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-semibold text-brand-800 shadow hover:bg-brand-50">
                            ✓ Marcar como acertado
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Despesas compartilhadas --}}
        <div class="card mt-6">
            <div class="card-pad pb-2 flex items-center justify-between">
                <h3 class="card-title">Despesas compartilhadas</h3>
                <span class="badge-slate">{{ $settlement['items']->count() }}</span>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse($settlement['items'] as $tx)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span class="w-12 shrink-0 text-xs tabular text-slate-500">{{ $tx->date->format('d/m') }}</span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('transactions.edit', $tx) }}" class="block truncate text-sm font-medium text-slate-800 hover:text-brand-700">{{ $tx->label() }}</a>
                            <div class="text-xs text-slate-500">pago por {{ $tx->user?->firstName() ?? '—' }}</div>
                        </div>
                        <span class="text-sm font-semibold tabular money-neg">{{ brl($tx->amount) }}</span>
                    </li>
                @empty
                    <li>
                        <x-empty icon="🧺" title="Nenhuma despesa do casal neste mês" text="Ao lançar um gasto, marque “Despesa compartilhada do casal” para ele entrar aqui.">
                            <a href="{{ route('transactions.create', ['tipo' => 'expense']) }}" class="btn-primary">+ Lançar despesa</a>
                        </x-empty>
                    </li>
                @endforelse
            </ul>
        </div>
    @endif

    <div class="mt-6 grid gap-6 @4xl:grid-cols-5">
        @if($pending->count())
            <div class="card card-pad @4xl:col-span-5 border-amber-200" id="aprovacoes">
                <h3 class="card-title">⏳ Despesas do casal aguardando aprovação</h3>
                <p class="muted mt-1">Só entram no acerto depois de aprovadas. Quem lançou não pode aprovar a própria despesa.</p>
                <ul class="mt-3 divide-y divide-slate-100">
                    @foreach($pending as $p)
                        <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-sm">
                                <span class="font-medium text-slate-800">{{ $p->label() }}</span>
                                <span class="text-slate-500">· {{ $p->date->format('d/m') }} · lançada por {{ $p->user?->firstName() }}</span>
                                <span class="ml-1 font-semibold tabular">{{ brl($p->amount) }}</span>
                            </div>
                            @if($p->user_id !== auth()->id() && can_write())
                                <form method="POST" action="{{ route('couple.approve', $p) }}" class="flex gap-2">
                                    @csrf
                                    <button name="decision" value="approve" class="btn-primary btn-sm">Aprovar</button>
                                    <button name="decision" value="reject" class="btn-secondary btn-sm" onclick="return confirm('Recusar? A despesa vira gasto individual de quem lançou.')">Recusar</button>
                                </form>
                            @else
                                <span class="badge-amber">aguardando {{ $p->user_id === auth()->id() ? 'seu par' : 'aprovação' }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Configurações --}}
        <form method="POST" action="{{ route('couple.update') }}" class="card card-pad space-y-5 @4xl:col-span-3"
              x-data="{ mode: @js(old('split_mode', $household->split_mode ?? 'proportional')), pct: {{ (int) old('split_owner_percent', $household->split_owner_percent ?? 50) }} }">
            @csrf @method('PUT')
            <h3 class="card-title">Configurações da carteira</h3>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="f-name">Nome da carteira</label>
                    <input id="f-name" name="name" value="{{ old('name', $household->name) }}" required maxlength="80" class="input {{ $errors->has('name') ? 'input-error' : '' }}">
                    <x-field-error name="name" />
                </div>
                <div>
                    <label class="label" for="f-type">Tipo</label>
                    <select id="f-type" name="type" class="input">
                        <option value="individual" @selected(old('type', $household->type) === 'individual')>🙂 Individual</option>
                        <option value="couple" @selected(old('type', $household->type) === 'couple')>💑 Casal</option>
                    </select>
                    <x-field-error name="type" />
                </div>
            </div>

            <div>
                <span class="label">Como dividir as despesas do casal</span>
                <div class="mt-1 grid gap-2">
                    @foreach($modes as $key => [$icon, $title, $desc])
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border px-4 py-3 transition"
                               :class="mode === '{{ $key }}' ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500' : 'border-slate-200 hover:border-slate-300'">
                            <input type="radio" name="split_mode" value="{{ $key }}" x-model="mode" class="mt-1 accent-brand-600">
                            <span class="text-xl">{{ $icon }}</span>
                            <span>
                                <span class="block text-sm font-semibold text-slate-800">{{ $title }}</span>
                                <span class="block text-xs text-slate-500">{{ $desc }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-field-error name="split_mode" />

                <div x-cloak x-show="mode === 'custom'" x-collapse class="mt-3 rounded-xl bg-slate-50 p-4">
                    <div class="flex items-center justify-between text-sm font-medium">
                        <span class="text-brand-800">{{ $owner?->firstName() ?? 'Você' }} <strong class="tabular" x-text="pct + '%'"></strong></span>
                        <span class="text-violet-700"><strong class="tabular" x-text="(100 - pct) + '%'"></strong> {{ $partner?->firstName() ?? 'Parceiro(a)' }}</span>
                    </div>
                    <input type="range" name="split_owner_percent" min="0" max="100" step="5" x-model.number="pct" class="mt-2 w-full accent-brand-600">
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-violet-400"><div class="h-full bg-brand-600" :style="`width: ${pct}%`"></div></div>
                    <x-field-error name="split_owner_percent" />
                </div>
            </div>

            @if($household->isCouple())
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-money-input name="approval_threshold" :value="$household->setting('approval_threshold') ?: null" label="Aprovar despesas compartilhadas a partir de"
                                       help="Gastos do casal iguais ou acima deste valor só entram no acerto depois que o outro aprovar. Deixe vazio para não exigir." />
                    </div>
                    @if($partner && $myRole === 'owner')
                        <div>
                            <label class="label" for="partner_role">Papel de {{ $partner->firstName() }}</label>
                            <select id="partner_role" name="partner_role" class="input">
                                @foreach(['member', 'viewer'] as $r)<option value="{{ $r }}" @selected($partnerRole === $r)>{{ \App\Models\Household::ROLES[$r] }}</option>@endforeach
                            </select>
                            <p class="help">"Somente leitura" vê tudo (exceto itens privados) mas não altera nada.</p>
                        </div>
                    @endif
                </div>
            @endif

            <label class="flex items-start gap-3">
                <input type="checkbox" name="regenerate_code" value="1" class="checkbox mt-0.5">
                <span>
                    <span class="block text-sm font-medium text-slate-800">Gerar um novo código de convite</span>
                    <span class="block text-xs text-slate-500">O link e o código atuais ({{ $household->invite_code }}) deixam de funcionar.</span>
                </span>
            </label>

            @if($myRole === 'owner')
                <div class="flex justify-end"><button class="btn-primary">Salvar configurações</button></div>
            @else
                <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">Só o titular da carteira pode alterar estas configurações. Seu papel: <b>{{ \App\Models\Household::ROLES[$myRole] ?? $myRole }}</b>.</p>
            @endif
        </form>

        <div class="space-y-6 @4xl:col-span-2">
            {{-- Dicas --}}
            <div class="card card-pad">
                <h3 class="card-title">💬 Dicas para conversar sobre dinheiro</h3>
                <ul class="mt-3 space-y-3 text-sm text-slate-600">
                    <li class="flex gap-2"><span>🗓️</span><span>Marquem um <strong>“encontro financeiro”</strong> mensal, curto e leve — de preferência com um café.</span></li>
                    <li class="flex gap-2"><span>🎯</span><span>Comecem pelos <strong>sonhos em comum</strong> (viagem, casa, filhos) antes de falar de cortes.</span></li>
                    <li class="flex gap-2"><span>🪙</span><span>Cada um com uma <strong>mesada pessoal</strong> sem prestar contas evita muita discussão.</span></li>
                    <li class="flex gap-2"><span>🤝</span><span>Dívida de um é desafio dos dois: <strong>sem culpa</strong>, com plano.</span></li>
                    <li class="flex gap-2"><span>📊</span><span>Se as rendas são diferentes, a divisão <strong>proporcional</strong> costuma ser a mais justa.</span></li>
                </ul>
            </div>

            {{-- Carteiras --}}
            <div class="card card-pad">
                <h3 class="card-title">Suas carteiras</h3>
                <ul class="mt-3 space-y-2">
                    @foreach($households as $h)
                        <li class="flex items-center justify-between gap-3 rounded-xl px-3 py-2 {{ $h->id === $household->id ? 'bg-brand-50 ring-1 ring-brand-200' : 'bg-slate-50' }}">
                            <span class="min-w-0 truncate text-sm">{{ $h->isCouple() ? '💑' : '🙂' }} <span class="font-medium text-slate-800">{{ $h->name }}</span></span>
                            @if($h->id === $household->id)
                                <span class="badge-green">ativa</span>
                            @else
                                <form method="POST" action="{{ route('household.switch', $h) }}">@csrf<button class="btn-ghost btn-sm">Ativar</button></form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('onboarding', ['nova' => 1]) }}" class="mt-3 inline-block text-sm font-medium text-brand-700 hover:underline">+ Criar outra carteira</a>
            </div>
        </div>
    </div>
</x-layouts.app>
