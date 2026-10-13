<x-layouts.app title="Primeiros passos">
    <div class="mx-auto max-w-3xl" x-data="{ step: 1, fixed: [{description: 'Aluguel', amount: '', day: 10}, {description: 'Energia', amount: '', day: 15}, {description: 'Internet', amount: '', day: 20}] }">
        <div class="mb-8">
            <div class="text-sm font-semibold text-brand-700">Vamos montar seu mapa financeiro</div>
            <h2 class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-900">Em 3 passos o Prumo já calcula quanto você pode gastar por dia</h2>
            @if($household->isCouple())
                <p class="mt-2 text-sm text-slate-500">💑 Carteira do casal: preencha só a <b>sua</b> renda — seu par preenche a dele(a) ao entrar com o convite.</p>
            @endif
            <div class="mt-6 flex gap-2">
                <template x-for="i in 3"><div class="h-1.5 flex-1 rounded-full" :class="i <= step ? 'bg-brand-600' : 'bg-slate-200'"></div></template>
            </div>
        </div>

        <form method="POST" action="{{ route('setup.store') }}">
            @csrf
            {{-- Passo 1 --}}
            <div x-show="step === 1" class="card card-pad space-y-5">
                <div>
                    <div class="text-2xl">💼</div>
                    <h3 class="mt-2 font-display text-xl font-semibold">Quanto entra por mês?</h3>
                    <p class="muted">Salário líquido (o que cai na conta) e uma média de renda extra, se tiver.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-money-input name="salary" label="Salário fixo líquido" class="sm:col-span-2" />
                    <div>
                        <label class="label">Dia que recebe</label>
                        <input type="number" name="salary_day" min="1" max="31" value="5" class="input">
                    </div>
                </div>
                <x-money-input name="extra" label="Renda extra média (freela, comissões, bicos)" help="Opcional. Você também pode lançar cada renda extra quando ela acontecer." />
                <div>
                    <label class="label">Horas trabalhadas por mês</label>
                    <input type="number" name="work_hours" value="176" min="20" max="400" class="input w-40">
                    <p class="help">Usamos para mostrar quantas <b>horas da sua vida</b> cada gasto custa. 😉</p>
                </div>
                <div class="flex justify-end"><button type="button" class="btn-primary" @click="step = 2">Próximo →</button></div>
            </div>

            {{-- Passo 2 --}}
            <div x-cloak x-show="step === 2" class="card card-pad space-y-5">
                <div>
                    <div class="text-2xl">📌</div>
                    <h3 class="mt-2 font-display text-xl font-semibold">Quais são seus gastos fixos?</h3>
                    <p class="muted">Contas que vêm todo mês: aluguel, condomínio, luz, internet, escola, academia, plano de saúde...</p>
                </div>
                <div class="space-y-3">
                    <template x-for="(f, i) in fixed" :key="i">
                        <div class="grid grid-cols-12 gap-2">
                            <input class="input col-span-6" :name="`fixed[${i}][description]`" x-model="f.description" placeholder="Descrição">
                            <div class="col-span-3" x-data="money('')">
                                <input class="input tabular" :name="`fixed[${i}][amount]`" x-model="raw" @input="format($event)" placeholder="R$ 0,00" inputmode="numeric">
                            </div>
                            <input class="input col-span-2" type="number" min="1" max="31" :name="`fixed[${i}][day]`" x-model="f.day" title="Dia do vencimento">
                            <button type="button" class="btn-ghost col-span-1 px-0" @click="fixed.splice(i, 1)" title="Remover">✕</button>
                        </div>
                    </template>
                    <button type="button" class="btn-secondary btn-sm" @click="fixed.push({description: '', amount: '', day: 10})">+ Adicionar gasto fixo</button>
                </div>
                <div class="flex justify-between">
                    <button type="button" class="btn-ghost" @click="step = 1">← Voltar</button>
                    <button type="button" class="btn-primary" @click="step = 3">Próximo →</button>
                </div>
            </div>

            {{-- Passo 3 --}}
            <div x-cloak x-show="step === 3" class="card card-pad space-y-5" x-data="{ q: '', code: '', name: '', open: false, banks: @js($banks) }">
                <div>
                    <div class="text-2xl">🏦</div>
                    <h3 class="mt-2 font-display text-xl font-semibold">Sua conta principal</h3>
                    <p class="muted">Onde o salário cai. O saldo de hoje é o ponto de partida (lista de bancos via BrasilAPI).</p>
                </div>
                <div class="relative">
                    <label class="label">Banco</label>
                    <input class="input" x-model="q" @focus="open = true" @click.outside="open = false" placeholder="Digite: Nubank, Itaú, Caixa...">
                    <input type="hidden" name="bank_code" :value="code">
                    <input type="hidden" name="bank_name" :value="name || q">
                    <div x-show="open && q.length > 0" x-cloak class="absolute z-10 mt-1 max-h-60 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                        <template x-for="b in banks.filter(b => (b.name + ' ' + b.code).toLowerCase().includes(q.toLowerCase())).slice(0, 12)" :key="b.code">
                            <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50" @click="q = b.name; name = b.name; code = b.code; open = false">
                                <span class="font-mono text-xs text-slate-400" x-text="b.code.padStart(3, '0')"></span> <span x-text="b.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <x-money-input name="balance" label="Saldo atual nessa conta" />
                <div class="rounded-xl bg-brand-50 p-4 text-sm text-brand-800">
                    ✨ Depois você pode cadastrar cartões de crédito, outras contas, dívidas e metas — e até <b>importar o extrato OFX</b> do banco.
                </div>
                <div class="flex justify-between">
                    <button type="button" class="btn-ghost" @click="step = 2">← Voltar</button>
                    <button class="btn-primary">Concluir e ver meu painel 🎉</button>
                </div>
            </div>
        </form>
        <div class="mt-4 text-center"><a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:underline">Pular por enquanto</a></div>
    </div>
</x-layouts.app>
