@php
    $user = auth()->user();
    $balance = (float) $debt->current_balance;
    $installmentCount = $installment > 0 ? (int) ceil($balance / $installment) : null;
    $fitsCapacity = $capacity > 0 && $installment <= $capacity;

    $script = "Olá, meu nome é {$user->name}. Estou entrando em contato sobre a minha dívida com a {$debt->creditor}"
        .($debt->is_overdue ? ', que está em atraso' : '')
        .". O saldo que tenho aqui é de ".brl($balance).".\n\n"
        ."Quero muito resolver essa situação, mas preciso de um acordo que eu consiga cumprir de verdade. Hoje minha realidade financeira é apertada"
        .($income > 0 ? ' e já organizei meu orçamento para saber exatamente quanto posso pagar' : '').".\n\n"
        ."Tenho duas propostas:\n"
        ."1) Pagamento à vista de ".brl($offerCash).", para quitar a dívida.\n"
        ."2) Ou um parcelamento com parcelas de até ".brl($installment)." por mês"
        .($installmentCount ? " (cerca de {$installmentCount} parcelas)" : '').", sem entrada alta.\n\n"
        ."Antes de fechar, poderia me informar o Custo Efetivo Total (CET), o valor final do acordo e o número de protocolo deste atendimento? "
        ."Também preciso receber o acordo por escrito (e-mail ou SMS) antes de pagar.\n\n"
        ."Obrigado(a) pela atenção.";
@endphp

<x-layouts.app title="Roteiro de negociação">
    <x-page-header title="Negociar com {{ $debt->creditor }}" subtitle="Um roteiro pronto para você ligar ou conversar no chat com mais segurança.">
        <x-slot:actions>
            <a href="{{ route('debts.index') }}" class="btn-secondary">← Voltar ao plano</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Resumo da dívida --}}
    <div class="mb-6 stat-grid @3xl:grid-cols-4">
        <x-stat label="Saldo devedor" :value="brl($balance)" icon="📄" :hint="e($debt->typeLabel())" />
        <x-stat label="Juros" :value="num($debt->monthly_interest_rate).'% a.m.'" icon="📈" :tone="(float) $debt->monthly_interest_rate > 5 ? 'red' : 'slate'"
                :hint="num($debt->annualRate(), 1).'% ao ano'" />
        <x-stat label="Sua capacidade mensal" :value="brl($capacity)" icon="🧮" tone="brand" hint="o que sobra após gastos fixos e do dia a dia" />
        <x-stat label="Situação" :value="$debt->is_overdue ? 'Em atraso' : 'Em dia'" icon="{{ $debt->is_overdue ? '⏰' : '✅' }}" :tone="$debt->is_overdue ? 'amber' : 'green'"
                :hint="$debt->is_overdue ? 'Atrasadas costumam ter descontos maiores' : 'Mesmo em dia, dá para pedir redução de juros'" />
    </div>

    <div class="grid gap-6 @4xl:grid-cols-5">
        <div class="space-y-6 @4xl:col-span-3">
            {{-- Propostas --}}
            <div class="card card-pad">
                <h3 class="card-title">Suas propostas</h3>
                <p class="muted mb-4 text-sm">Comece sempre pela proposta mais baixa. Quem dá o primeiro número define a conversa.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-gradient-to-br from-emerald-50 to-brand-50 p-4 ring-1 ring-emerald-100">
                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">💰 À vista</div>
                        <div class="mt-1 font-display text-2xl font-bold tabular text-slate-900">{{ brl($offerCash) }}</div>
                        <div class="mt-1 text-xs text-slate-600">Ofereça este valor. Teto aceitável: <strong class="tabular">{{ brl($offerCashMax) }}</strong>.</div>
                        <div class="mt-2 text-[11px] text-slate-500">Só aceite se você tiver o dinheiro sem comprometer o essencial.</div>
                    </div>
                    <div class="rounded-2xl bg-gradient-to-br from-sky-50 to-violet-50 p-4 ring-1 ring-sky-100">
                        <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">📆 Parcelado</div>
                        <div class="mt-1 font-display text-2xl font-bold tabular text-slate-900">{{ brl($installment) }}<span class="text-sm font-medium text-slate-500">/mês</span></div>
                        <div class="mt-1 text-xs text-slate-600">
                            @if($installmentCount) Cerca de {{ $installmentCount }} {{ $installmentCount === 1 ? 'parcela' : 'parcelas' }}, sem contar juros. @endif
                        </div>
                        <div class="mt-2 text-[11px] {{ $fitsCapacity ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ $fitsCapacity ? '✓ Cabe no seu orçamento atual.' : '⚠ Fica apertado no seu orçamento — negocie prazo maior.' }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Roteiro --}}
            <div class="card card-pad" x-data="{ copied: false, copy() { navigator.clipboard.writeText(this.$refs.script.innerText).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000) }) } }">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="card-title">Roteiro para ligação ou chat</h3>
                        <p class="muted text-sm">Já preenchido com seus números. Copie, adapte e use.</p>
                    </div>
                    <button type="button" class="btn-secondary btn-sm shrink-0" @click="copy()">
                        <span x-show="!copied">📋 Copiar</span>
                        <span x-show="copied" x-cloak class="text-emerald-600">✓ Copiado!</span>
                    </button>
                </div>
                <div x-ref="script" class="whitespace-pre-line rounded-2xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-700 ring-1 ring-slate-100">{{ $script }}</div>
                <div class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-xs text-amber-800">
                    💡 <strong>Se disserem não:</strong> agradeça, anote o protocolo e diga que vai aguardar uma proposta melhor. Credores costumam melhorar a oferta em campanhas (ex.: feirões de fim de ano).
                </div>
            </div>
        </div>

        <div class="space-y-6 @4xl:col-span-2">
            {{-- Direitos --}}
            <div class="card card-pad" x-data="{ checks: {} }">
                <h3 class="card-title">Checklist dos seus direitos</h3>
                <p class="muted mb-4 text-sm">Marque conforme for fazendo durante a negociação.</p>
                <ul class="space-y-2.5">
                    @foreach([
                        ['cet', 'Pedir o CET (Custo Efetivo Total)', 'É obrigação do credor informar. Compare com outras opções.'],
                        ['protocolo', 'Anotar o número de protocolo', 'De cada ligação ou atendimento, com data e nome do atendente.'],
                        ['escrito', 'Receber o acordo por escrito', 'Antes de pagar qualquer boleto. Confira valores e datas.'],
                        ['capacidade', 'Não aceitar parcela acima de '.brl($capacity), 'Acordo quebrado volta a dívida com juros. Melhor um prazo maior.'],
                        ['boleto', 'Pagar só por canais oficiais', 'Desconfie de boletos enviados por WhatsApp de números desconhecidos.'],
                        ['nome', 'Nome limpo em até 5 dias úteis', 'Após pagar a 1ª parcela ou o acordo, a negativação deve ser retirada.'],
                    ] as [$k, $title, $desc])
                        <li>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl p-2 hover:bg-slate-50">
                                <input type="checkbox" class="checkbox mt-0.5" x-model="checks.{{ $k }}">
                                <span>
                                    <span class="block text-sm font-semibold text-slate-800" :class="checks.{{ $k }} && 'line-through text-slate-400'">{{ $title }}</span>
                                    <span class="block text-xs text-slate-500">{{ $desc }}</span>
                                </span>
                            </label>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    ⏳ <strong>Sobre prescrição:</strong> após 5 anos, a dívida sai dos cadastros de inadimplentes (Serasa, SPC) e não pode mais ser cobrada na Justiça — mas ela continua existindo e o credor ainda pode oferecer acordos e negar novo crédito.
                </div>
            </div>

            {{-- Links úteis --}}
            <div class="card card-pad">
                <h3 class="card-title mb-3">Onde buscar ajuda</h3>
                <div class="space-y-2">
                    @foreach([
                        ['https://registrato.bcb.gov.br', '🏦', 'Registrato (Banco Central)', 'Veja grátis todas as dívidas no seu CPF'],
                        ['https://www.serasa.com.br/limpa-nome-online/', '🧹', 'Serasa Limpa Nome', 'Ofertas de acordo com desconto'],
                        ['https://www.consumidor.gov.br', '📣', 'consumidor.gov.br', 'Reclamação oficial que as empresas respondem'],
                        ['https://www.procon.sp.gov.br', '⚖️', 'Procon-SP (ou o do seu estado)', 'Superendividamento: Lei 14.181/2021'],
                    ] as [$url, $icon, $name, $desc])
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-brand-300 hover:bg-brand-50/50">
                            <span class="text-xl">{{ $icon }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-slate-800">{{ $name }}</span>
                                <span class="block text-xs text-slate-500">{{ $desc }}</span>
                            </span>
                            <span class="text-slate-400">↗</span>
                        </a>
                    @endforeach
                </div>
                <p class="help mt-3">Não tem como pagar nada? A Defensoria Pública do seu estado atende gratuitamente casos de superendividamento.</p>
            </div>
        </div>
    </div>
</x-layouts.app>
