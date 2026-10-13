<x-layouts.app title="Importar extrato">
    <x-page-header title="Importar extrato" subtitle="Traga os lançamentos do seu banco em segundos, sem digitar um por um." />

    <div class="grid gap-6 @4xl:grid-cols-5">
        {{-- Upload --}}
        <div class="@4xl:col-span-3">
            <form method="POST" action="{{ route('imports.preview') }}" enctype="multipart/form-data" class="card card-pad space-y-5"
                  x-data="{ name: '', dragging: false, pick(files) { if (files && files.length) { this.$refs.file.files = files; this.name = files[0].name; } } }">
                @csrf
                <div>
                    <label class="label">Arquivo do extrato</label>
                    <label for="f-file"
                           @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                           @drop.prevent="dragging = false; pick($event.dataTransfer.files)"
                           :class="dragging ? 'border-brand-500 bg-brand-50' : (name ? 'border-emerald-300 bg-emerald-50/50' : 'border-slate-300 bg-slate-50 hover:border-brand-400 hover:bg-brand-50/40')"
                           class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-10 text-center transition {{ $errors->has('file') ? 'border-rose-300' : '' }}">
                        <div class="text-4xl" x-text="name ? '📄' : '📥'">📥</div>
                        <template x-if="!name">
                            <div>
                                <div class="mt-2 text-sm font-semibold text-slate-800">Arraste o arquivo aqui ou <span class="text-brand-700 underline">clique para escolher</span></div>
                                <div class="mt-1 text-xs text-slate-500">OFX, QFX, CSV ou planilha XLSX · até 10 MB</div>
                            </div>
                        </template>
                        <template x-if="name">
                            <div>
                                <div class="mt-2 break-all text-sm font-semibold text-emerald-800" x-text="name"></div>
                                <div class="mt-1 text-xs text-slate-500">Clique para trocar o arquivo</div>
                            </div>
                        </template>
                    </label>
                    <input id="f-file" x-ref="file" type="file" name="file" accept=".ofx,.qfx,.csv,.txt,.xlsx" required class="sr-only"
                           @change="name = $event.target.files[0]?.name || ''">
                    <x-field-error name="file" />
                </div>

                <div>
                    <label class="label" for="f-method">Esses lançamentos são de qual conta ou cartão?</label>
                    <select id="f-method" name="method" required class="input {{ $errors->has('method') ? 'input-error' : '' }}">
                        <option value="">Selecione…</option>
                        @if($accounts->isNotEmpty())
                            <optgroup label="Contas">
                                @foreach($accounts as $a)
                                    <option value="account:{{ $a->id }}" @selected(old('method') === "account:{$a->id}")>🏦 {{ $a->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if($cards->isNotEmpty())
                            <optgroup label="Cartões de crédito">
                                @foreach($cards as $c)
                                    <option value="card:{{ $c->id }}" @selected(old('method') === "card:{$c->id}")>💳 {{ $c->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                    <x-field-error name="method" />
                    @if($accounts->isEmpty() && $cards->isEmpty())
                        <p class="help">Você ainda não tem contas. <a href="{{ route('accounts.create') }}" class="font-medium text-brand-700 underline">Cadastre uma conta</a> primeiro.</p>
                    @endif
                </div>

                @if(household()->isCouple())
                    <label class="flex items-start gap-3 rounded-xl bg-slate-50 px-4 py-3">
                        <input type="checkbox" name="is_shared" value="1" class="checkbox mt-0.5" @checked(old('is_shared'))>
                        <span>
                            <span class="block text-sm font-medium text-slate-800">Despesa compartilhada do casal</span>
                            <span class="block text-xs text-slate-500">Marque se este extrato é de uma conta/cartão usado pelos dois. Entra no acerto do mês.</span>
                        </span>
                    </label>
                @endif

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">Você revisa tudo antes de confirmar. Nada é salvo nesta etapa.</p>
                    <button class="btn-primary">Pré-visualizar lançamentos →</button>
                </div>
            </form>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="card card-pad">
                    <div class="text-xl">🛡️</div>
                    <div class="mt-1 text-sm font-semibold text-slate-800">Sem duplicados</div>
                    <p class="mt-1 text-xs text-slate-500">Cada lançamento ganha uma “impressão digital”. Se você importar o mesmo extrato duas vezes, o Prumo reconhece e ignora o que já entrou.</p>
                </div>
                <div class="card card-pad">
                    <div class="text-xl">🧠</div>
                    <div class="mt-1 text-sm font-semibold text-slate-800">Categorização que aprende</div>
                    <p class="mt-1 text-xs text-slate-500">O Prumo sugere a categoria de cada gasto e lembra das suas correções. Quanto mais você importa, menos precisa ajustar.</p>
                </div>
            </div>
        </div>

        {{-- Como exportar --}}
        <div class="card card-pad @4xl:col-span-2" x-data="{ open: null }">
            <h3 class="card-title">Como baixar o extrato do seu banco</h3>
            <p class="muted mt-1 text-xs">Prefira o formato <strong>OFX</strong> (às vezes chamado de “Money” ou “Microsoft Money”). Ele traz datas e valores sem erro.</p>
            <ol class="mt-4 space-y-2 text-sm text-slate-700">
                <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-800">1</span> Abra o app ou o internet banking.</li>
                <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-800">2</span> Vá em <strong>Extrato</strong> (ou <strong>Fatura</strong>, no cartão).</li>
                <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-800">3</span> Escolha o período e toque em <strong>Exportar / Salvar / Compartilhar</strong>.</li>
                <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-800">4</span> Selecione <strong>OFX</strong> (ou “Money”). Se não houver, use <strong>CSV</strong>.</li>
            </ol>
            <div class="mt-5 divide-y divide-slate-100 border-t border-slate-100">
                @foreach([
                    ['Nubank', '🟣', 'No app: Conta → Extrato → ícone de compartilhar → exportar. Para o cartão, a fatura pode ser enviada por e-mail em OFX/CSV pelo app ou site.'],
                    ['Itaú', '🟠', 'Internet banking: Conta corrente → Extrato → “Salvar em outros formatos” → OFX/Money.'],
                    ['Bradesco', '🔴', 'Internet banking: Saldos e extratos → Extrato → “Exportar” → formato Money (OFX).'],
                    ['Banco do Brasil', '🟡', 'Internet banking: Conta corrente → Extrato → “Salvar” ou “Exportar” → OFX (Money).'],
                    ['Caixa', '🔵', 'Internet banking: Extrato → selecione o período → “Exportar” → OFX.'],
                    ['Inter', '🟧', 'App ou site: Extrato → filtro de período → “Exportar” → OFX ou CSV.'],
                    ['Santander', '🟥', 'Internet banking: Conta corrente → Extrato → “Exportar” → OFX (Money).'],
                ] as $i => [$bank, $dot, $howto])
                    <div>
                        <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}" class="flex w-full items-center justify-between py-2.5 text-left text-sm font-medium text-slate-800">
                            <span>{{ $dot }} {{ $bank }}</span>
                            <svg class="h-4 w-4 text-slate-400 transition" :class="open === {{ $i }} && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                        <p x-cloak x-show="open === {{ $i }}" x-collapse class="pb-3 text-xs text-slate-500">{{ $howto }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-[11px] text-slate-400">Os menus mudam com frequência — se não encontrar, procure por “exportar”, “OFX” ou “Money” na tela de extrato.</p>
        </div>
    </div>

    {{-- Histórico --}}
    <div class="card mt-6">
        <div class="card-pad pb-2"><h3 class="card-title">Últimas importações</h3></div>
        @if($history->isEmpty())
            <x-empty icon="🗂️" title="Nenhuma importação ainda" text="Seu histórico de arquivos importados aparece aqui." />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Arquivo</th><th>Formato</th><th class="text-right">Importados</th><th class="text-right">Ignorados</th><th>Quando</th><th>Por</th></tr></thead>
                    <tbody>
                    @foreach($history as $h)
                        <tr>
                            <td class="max-w-[220px] truncate font-medium text-slate-800" title="{{ $h->filename }}">{{ $h->filename }}</td>
                            <td><span class="badge-slate uppercase">{{ $h->format }}</span></td>
                            <td class="text-right tabular text-emerald-700">{{ $h->imported }}</td>
                            <td class="text-right tabular text-slate-500">{{ $h->skipped }}</td>
                            <td class="whitespace-nowrap text-slate-500">{{ $h->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-slate-600">{{ $h->user?->firstName() ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
