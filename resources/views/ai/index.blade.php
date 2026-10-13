<x-layouts.app title="Consultor IA">
    <x-page-header title="Consultor financeiro" subtitle="Converse sobre o seu dinheiro. O Prumo responde com base nos seus números reais.">
        <x-slot:actions>
            @if(empty($providers))
                <a href="{{ route('profile.edit') }}#integracoes" class="badge-amber inline-flex items-center gap-1.5" title="Configure uma IA gratuita no seu perfil">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Consultor Offline (sem IA externa)
                </a>
            @else
                <span class="badge-green inline-flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> IA: {{ implode(', ', $providers) }}
                </span>
            @endif
            <a href="{{ route('ai.index') }}" class="btn-primary btn-sm">+ Nova conversa</a>
        </x-slot:actions>
    </x-page-header>

    @if(empty($providers))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            O consultor está no <strong>modo offline</strong>: ele analisa seus números com regras prontas, sem IA externa.
            Para respostas mais completas, ative uma IA <strong>gratuita</strong> (chave do Gemini ou Groq, ou o Ollama rodando no seu servidor).
            <a href="{{ route('profile.edit') }}#integracoes" class="font-semibold underline">Veja como ativar no seu perfil →</a>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[260px_1fr]">
        {{-- Conversas --}}
        <aside class="card overflow-hidden" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="flex w-full items-center justify-between px-4 py-3 text-left lg:pointer-events-none">
                <span class="text-sm font-semibold text-slate-800">Conversas <span class="muted font-normal">({{ $conversations->count() }})</span></span>
                <svg class="h-4 w-4 text-slate-400 transition lg:hidden" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="border-t border-slate-100 lg:block" :class="open ? 'block' : 'hidden'">
                <a href="{{ route('ai.index') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-brand-700 hover:bg-brand-50">
                    <span>✨</span> Nova conversa
                </a>
                <div class="max-h-[calc(100vh-20rem)] overflow-y-auto">
                    @forelse($conversations as $c)
                        @php $active = $conversation && $conversation->id === $c->id; @endphp
                        <div class="group flex items-center gap-1 border-t border-slate-50 pr-2 {{ $active ? 'bg-brand-50' : 'hover:bg-slate-50' }}">
                            <a href="{{ route('ai.index', $c) }}" class="min-w-0 flex-1 px-4 py-2.5">
                                <div class="truncate text-sm {{ $active ? 'font-semibold text-brand-800' : 'text-slate-700' }}">{{ $c->title ?: 'Conversa' }}</div>
                                <div class="text-[11px] text-slate-400">{{ $c->updated_at?->diffForHumans() }}</div>
                            </a>
                            <form method="POST" action="{{ route('ai.destroy', $c) }}" onsubmit="return confirm('Apagar esta conversa?')">
                                @csrf @method('DELETE')
                                <button class="rounded-lg p-1.5 text-slate-300 hover:bg-rose-50 hover:text-rose-600" title="Apagar conversa" aria-label="Apagar conversa">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-xs text-slate-400">Suas conversas aparecem aqui.</p>
                    @endforelse
                </div>
            </div>
        </aside>

        {{-- Chat --}}
        <section class="card flex flex-col overflow-hidden"
                 x-data="aiChat(@js($conversation?->id), @js(route('ai.send')))"
                 x-init="$nextTick(() => $refs.messages.scrollTop = $refs.messages.scrollHeight)">
            <div x-ref="messages" class="h-[calc(100vh-20rem)] min-h-[320px] space-y-4 overflow-y-auto bg-slate-50/60 p-4 sm:p-5 lg:h-[calc(100vh-16rem)]">
                @if($conversation && $conversation->messages->isNotEmpty())
                    @foreach($conversation->messages as $m)
                        @if($m->role === 'user')
                            <div class="flex justify-end"><div class="chat-user">{{ $m->content }}</div></div>
                        @else
                            <div class="flex gap-3">
                                <div class="ai-avatar">P</div>
                                <div class="chat-ai prose-chat">
                                    {!! \Illuminate\Support\Str::markdown($m->content, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                                    @if($m->provider)<div class="mt-2 text-[11px] text-slate-400">via {{ $m->provider }}</div>@endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                @else
                    <div x-data="{ hidden: false }" x-show="!hidden" x-effect="if (loading) hidden = true" class="mx-auto max-w-2xl py-4">
                        <div class="rounded-2xl bg-white p-5 ring-1 ring-slate-200 sm:p-6">
                            <div class="flex items-center gap-3">
                                <div class="ai-avatar h-10 w-10 text-sm">P</div>
                                <div>
                                    <div class="font-display text-lg font-semibold text-slate-900">Olá! Sou o seu consultor do Prumo 👋</div>
                                    <div class="text-sm text-slate-500">Pode perguntar sem vergonha — aqui não tem julgamento, só plano.</div>
                                </div>
                            </div>
                            <p class="mt-4 text-sm text-slate-600">Antes de cada resposta eu consulto, ao vivo, os números da sua carteira:</p>
                            <ul class="mt-3 grid gap-2 text-sm text-slate-700 sm:grid-cols-2">
                                <li class="flex items-center gap-2"><span>💼</span> Renda e entradas do mês</li>
                                <li class="flex items-center gap-2"><span>🛒</span> Gastos por categoria e orçamentos</li>
                                <li class="flex items-center gap-2"><span>⛓️</span> Dívidas, juros e parcelas</li>
                                <li class="flex items-center gap-2"><span>🎯</span> Metas e reserva de emergência</li>
                                <li class="flex items-center gap-2"><span>📈</span> Investimentos e patrimônio</li>
                                <li class="flex items-center gap-2"><span>💳</span> Faturas de cartão e vencimentos</li>
                            </ul>
                            <div class="mt-5 text-xs font-semibold uppercase tracking-wider text-slate-400">Comece por aqui</div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($suggestions as $s)
                                    <button type="button" @click="send(@js($s))" :disabled="loading"
                                            class="rounded-full border border-brand-200 bg-brand-50 px-3 py-1.5 text-left text-xs font-medium text-brand-800 transition hover:bg-brand-100 disabled:opacity-50">
                                        {{ $s }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Digitando --}}
            <div x-cloak x-show="loading" class="flex items-center gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-3">
                <div class="ai-avatar">P</div>
                <div class="flex items-center gap-1 rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-200" aria-label="O consultor está digitando">
                    <span class="h-2 w-2 animate-bounce rounded-full bg-brand-500 [animation-delay:-0.3s]"></span>
                    <span class="h-2 w-2 animate-bounce rounded-full bg-brand-500 [animation-delay:-0.15s]"></span>
                    <span class="h-2 w-2 animate-bounce rounded-full bg-brand-500"></span>
                </div>
                <span class="text-xs text-slate-400">Analisando seus números…</span>
            </div>

            {{-- Erro --}}
            <div x-cloak x-show="error" class="flex items-center justify-between gap-3 border-t border-rose-100 bg-rose-50 px-5 py-2.5 text-sm text-rose-700">
                <span x-text="error"></span>
                <button type="button" @click="error = ''" class="text-rose-500 hover:text-rose-700" aria-label="Fechar">✕</button>
            </div>

            {{-- Entrada --}}
            <form @submit.prevent="send()" class="border-t border-slate-200 bg-white p-3 sm:p-4">
                <div class="flex items-end gap-2">
                    <textarea x-model="input" rows="2" maxlength="4000"
                              @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); send(); }"
                              placeholder="Pergunte qualquer coisa sobre o seu dinheiro…"
                              class="input min-h-[44px] flex-1 resize-none"></textarea>
                    <button type="submit" class="btn-primary h-11 px-4" :disabled="loading || !input.trim()" aria-label="Enviar">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/></svg>
                        <span class="hidden sm:inline">Enviar</span>
                    </button>
                </div>
                <p class="mt-2 text-[11px] leading-snug text-slate-400">
                    Enter envia · Shift+Enter quebra linha. As respostas são orientações educativas e não constituem recomendação de investimento
                    — o Prumo não é consultor registrado na CVM. Decisões importantes merecem uma conversa com um profissional.
                </p>
            </form>
        </section>
    </div>
</x-layouts.app>
