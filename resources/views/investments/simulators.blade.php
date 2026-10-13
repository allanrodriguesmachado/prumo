@php
    $cdi = (float) $cdi;
    $selic = (float) $selic;
    $ipca = (float) $ipca;
    $poupanca = round((pow(1.005, 12) - 1) * 100, 2); // 0,5% a.m. ≈ 6,17% a.a.
    $tesouroIpca = round((1 + $ipca / 100) * 1.06 * 100 - 100, 2);
    $defaultContribution = $monthlyIncome > 0 ? max(50, round($monthlyIncome * 0.1 / 50) * 50) : 300;
    $cfg = [
        'cdi' => $cdi, 'selic' => $selic, 'ipca' => $ipca,
        'poupanca' => $poupanca, 'tesouroIpca' => $tesouroIpca,
        'spending' => round((float) $monthlySpending, 2),
        'income' => round((float) $monthlyIncome, 2),
        'invested' => round((float) $invested, 2),
        'contribution' => $defaultContribution,
    ];
@endphp

<x-layouts.app title="Simuladores">
    <x-page-header title="Simuladores" subtitle="Brinque com os números e veja o poder do tempo e dos juros compostos a seu favor.">
        <x-slot:actions>
            <a href="{{ route('investments.index') }}" class="btn-secondary">← Carteira</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <div class="flex min-w-max gap-2">
            <a href="#juros" class="btn-secondary btn-sm">📈 Juros compostos</a>
            <a href="#fire" class="btn-secondary btn-sm">🏝️ Independência financeira</a>
            <a href="#oportunidade" class="btn-secondary btn-sm">☕ Custo de oportunidade</a>
            <span class="badge-slate self-center">CDI {{ pct($cdi, 2) }} · Selic {{ pct($selic, 2) }} · IPCA {{ pct($ipca, 2) }}</span>
        </div>
    </div>

    <div class="space-y-6">
        {{-- a) Juros compostos --}}
        <section id="juros" class="card card-pad scroll-mt-20" x-data="simCompound(@js($cfg))">
            <div class="card-title">📈 Juros compostos</div>
            <p class="muted mt-0.5 text-sm">Quanto o seu dinheiro pode virar com aportes mensais e paciência.</p>

            <div class="mt-5 grid gap-6 @4xl:grid-cols-5">
                <div class="space-y-4 @4xl:col-span-2">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="c-initial">Valor inicial (R$)</label>
                            <input id="c-initial" type="number" min="0" step="100" inputmode="decimal" class="input tabular" x-model.number="initial">
                        </div>
                        <div>
                            <label class="label" for="c-monthly">Aporte mensal (R$)</label>
                            <input id="c-monthly" type="number" min="0" step="50" inputmode="decimal" class="input tabular" x-model.number="monthly">
                        </div>
                        <div>
                            <label class="label" for="c-years">Prazo (anos)</label>
                            <input id="c-years" type="number" min="1" max="60" step="1" class="input tabular" x-model.number="years">
                        </div>
                        <div>
                            <label class="label" for="c-rate">Taxa (% a.a.)</label>
                            <input id="c-rate" type="number" min="0" max="100" step="0.1" inputmode="decimal" class="input tabular" x-model.number="rate">
                        </div>
                    </div>
                    <div>
                        <div class="mb-1.5 text-xs font-medium text-slate-500">Atalhos de taxa</div>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="p in presets" :key="p.label">
                                <button type="button" @click="rate = p.rate"
                                        :class="Math.abs(rate - p.rate) < 0.001 ? 'border-brand-400 bg-brand-50 text-brand-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                        class="rounded-full border px-3 py-1 text-xs font-medium">
                                    <span x-text="p.label"></span> <span class="tabular opacity-70" x-text="fmtPct(p.rate)"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="@4xl:col-span-3">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-xl bg-brand-50 p-3">
                            <div class="text-[11px] font-medium text-brand-700">Valor final</div>
                            <div class="font-display text-lg font-bold tabular text-brand-800 sm:text-xl" x-text="brl(result.final)"></div>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <div class="text-[11px] font-medium text-slate-500">Você investiu</div>
                            <div class="font-display text-lg font-bold tabular text-slate-800 sm:text-xl" x-text="brl(result.invested)"></div>
                        </div>
                        <div class="rounded-xl bg-emerald-50 p-3">
                            <div class="text-[11px] font-medium text-emerald-700">Juros ganhos</div>
                            <div class="font-display text-lg font-bold tabular text-emerald-700 sm:text-xl" x-text="brl(result.interest)"></div>
                        </div>
                    </div>

                    {{-- Gráfico em SVG --}}
                    <div class="mt-4">
                        <svg viewBox="0 0 600 220" class="h-48 w-full sm:h-56" preserveAspectRatio="none" role="img" aria-label="Evolução do patrimônio">
                            <line x1="0" y1="219.5" x2="600" y2="219.5" stroke="#e2e8f0" />
                            <line x1="0" y1="110" x2="600" y2="110" stroke="#e2e8f0" stroke-dasharray="4 4" />
                            <path :d="chart.total" fill="#289b7e" fill-opacity="0.25" stroke="#1f7d66" stroke-width="2" vector-effect="non-scaling-stroke" />
                            <path :d="chart.invested" fill="#94a3b8" fill-opacity="0.35" stroke="#64748b" stroke-width="1.5" vector-effect="non-scaling-stroke" />
                        </svg>
                        <div class="mt-1 flex justify-between text-[11px] text-slate-400 tabular">
                            <span>Hoje</span>
                            <span x-text="'Metade: ' + brl(chart.max / 2)"></span>
                            <span x-text="years + (years == 1 ? ' ano' : ' anos')"></span>
                        </div>
                        <div class="mt-2 flex gap-4 text-xs text-slate-600">
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-brand-600"></span>Patrimônio</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span>Dinheiro do seu bolso</span>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/60 p-3 text-sm text-slate-700">
                        Em valores de hoje (descontando IPCA de <span class="tabular" x-text="fmtPct(cfg.ipca)"></span> ao ano), seriam
                        <strong class="tabular" x-text="brl(result.real)"></strong> de poder de compra.
                        <span x-show="result.interest > result.invested">✨ Os juros já superam o que saiu do seu bolso!</span>
                    </div>
                    <p class="help mt-2">Simulação bruta, sem imposto de renda e com taxa constante. Rentabilidade passada não garante rentabilidade futura.</p>
                </div>
            </div>
        </section>

        {{-- b) Independência financeira --}}
        <section id="fire" class="card card-pad scroll-mt-20" x-data="simFire(@js($cfg))">
            <div class="card-title">🏝️ Independência financeira</div>
            <p class="muted mt-0.5 text-sm">Quanto você precisa acumular para viver dos rendimentos — e quanto tempo isso leva.</p>

            <div class="mt-5 grid gap-6 @4xl:grid-cols-5">
                <div class="grid grid-cols-2 gap-3 self-start @4xl:col-span-2">
                    <div>
                        <label class="label" for="f-cost">Custo de vida mensal (R$)</label>
                        <input id="f-cost" type="number" min="0" step="100" inputmode="decimal" class="input tabular" x-model.number="cost">
                    </div>
                    <div>
                        <label class="label" for="f-current">Já investido (R$)</label>
                        <input id="f-current" type="number" min="0" step="100" inputmode="decimal" class="input tabular" x-model.number="current">
                    </div>
                    <div>
                        <label class="label" for="f-contrib">Aporte mensal (R$)</label>
                        <input id="f-contrib" type="number" min="0" step="50" inputmode="decimal" class="input tabular" x-model.number="contrib">
                    </div>
                    <div>
                        <label class="label" for="f-ret">Retorno real (% a.a.)</label>
                        <input id="f-ret" type="number" min="0" max="30" step="0.5" inputmode="decimal" class="input tabular" x-model.number="realReturn">
                    </div>
                    <div class="col-span-2">
                        <label class="label" for="f-swr">Taxa de retirada segura (% a.a.)</label>
                        <input id="f-swr" type="number" min="1" max="10" step="0.5" inputmode="decimal" class="input tabular" x-model.number="swr">
                        <p class="help">A "regra dos 4%": retirar 4% ao ano tende a preservar o patrimônio por décadas. Retorno real = acima da inflação.</p>
                    </div>
                </div>

                <div class="@4xl:col-span-3">
                    <div class="rounded-2xl hero p-5 text-white">
                        <div class="text-xs font-medium text-brand-200">Seu número da liberdade</div>
                        <div class="mt-1 font-display text-3xl font-bold tabular" x-text="brl(target)"></div>
                        <div class="mt-1 text-xs text-brand-200">= custo anual de <span class="tabular" x-text="brl(cost * 12)"></span> ÷ <span x-text="fmtPct(swr)"></span></div>
                        <div class="mt-4">
                            <div class="mb-1.5 flex justify-between text-xs text-brand-100">
                                <span>Progresso</span>
                                <span class="font-semibold tabular" x-text="fmtPct(progress)"></span>
                            </div>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-white/15">
                                <div class="h-full rounded-full bg-brand-300 transition-all" :style="`width: ${Math.min(100, progress)}%`"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <div class="text-[11px] font-medium text-slate-500">Tempo estimado</div>
                            <div class="font-display text-xl font-bold text-slate-800" x-text="timeLabel"></div>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <div class="text-[11px] font-medium text-slate-500">Renda passiva hoje</div>
                            <div class="font-display text-xl font-bold tabular text-slate-800"><span x-text="brl(current * swr / 100 / 12)"></span><span class="text-xs font-medium text-slate-400">/mês</span></div>
                        </div>
                    </div>

                    <p class="mt-4 text-sm text-slate-600" x-text="message"></p>
                    <div class="mt-3 flex flex-wrap gap-2" x-show="months !== 0">
                        <template x-for="extra in [100, 300, 500]" :key="extra">
                            <span class="badge-slate">+<span x-text="brl(extra).replace(',00', '')"></span>/mês → <span x-text="yearsWith(extra)"></span></span>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        {{-- c) Custo de oportunidade --}}
        <section id="oportunidade" class="card card-pad scroll-mt-20" x-data="simOpportunity(@js($cfg))">
            <div class="card-title">☕ Custo de oportunidade</div>
            <p class="muted mt-0.5 text-sm">Sem culpa: só uma curiosidade sobre quanto aquele gasto recorrente viraria se fosse investido.</p>

            <div class="mt-4 flex flex-wrap gap-2">
                <template x-for="p in presets" :key="p.label">
                    <button type="button" @click="amount = p.amount; label = p.label"
                            :class="label === p.label ? 'border-brand-400 bg-brand-50 text-brand-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                            class="rounded-full border px-3 py-1 text-xs font-medium">
                        <span x-text="p.icon + ' ' + p.label"></span> · <span class="tabular" x-text="brl(p.amount).replace(',00', '')"></span>
                    </button>
                </template>
            </div>

            <div class="mt-5 grid gap-6 @4xl:grid-cols-5">
                <div class="grid grid-cols-2 gap-3 self-start @4xl:col-span-2">
                    <div class="col-span-2">
                        <label class="label" for="o-amount">Gasto por mês (R$)</label>
                        <input id="o-amount" type="number" min="0" step="10" inputmode="decimal" class="input tabular" x-model.number="amount" @input="label = ''">
                    </div>
                    <div>
                        <label class="label" for="o-years">Por quantos anos</label>
                        <input id="o-years" type="number" min="1" max="50" step="1" class="input tabular" x-model.number="years">
                    </div>
                    <div>
                        <label class="label" for="o-rate">Rendendo (% a.a.)</label>
                        <input id="o-rate" type="number" min="0" max="50" step="0.1" inputmode="decimal" class="input tabular" x-model.number="rate">
                        <p class="help">Padrão: 100% do CDI.</p>
                    </div>
                </div>

                <div class="@4xl:col-span-3">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5">
                        <div class="text-sm text-slate-600">
                            <span x-text="brl(amount)"></span> por mês durante <span x-text="years + (years == 1 ? ' ano' : ' anos')"></span> viraria
                        </div>
                        <div class="mt-1 font-display text-3xl font-bold tabular text-emerald-700" x-text="brl(result.final)"></div>
                        <div class="mt-1 text-sm text-slate-600">
                            Do seu bolso sairiam <span class="tabular font-medium" x-text="brl(result.paid)"></span>;
                            os juros trariam <strong class="tabular text-emerald-700" x-text="brl(result.final - result.paid)"></strong> de presente. 🎁
                        </div>
                    </div>
                    <ul class="mt-4 space-y-2 text-sm text-slate-600">
                        <li x-show="cfg.spending > 0">🏠 Isso pagaria <strong class="tabular" x-text="monthsOfLife"></strong> do seu custo de vida atual.</li>
                        <li>💸 Renderia cerca de <strong class="tabular" x-text="brl(result.final * (Math.pow(1 + rate / 100, 1 / 12) - 1))"></strong> por mês, sem mexer no principal.</li>
                        <li>🌱 Não é sobre cortar tudo — é sobre escolher. Que tal investir só metade? Daria <strong class="tabular" x-text="brl(result.final / 2)"></strong>.</li>
                    </ul>
                    <p class="help mt-3">Valores brutos, sem IR e com taxa constante — servem para dar uma ideia de grandeza.</p>
                </div>
            </div>
        </section>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            const monthlyRate = (annual) => Math.pow(1 + (Number(annual) || 0) / 100, 1 / 12) - 1;
            const fmtPct = (v) => (Number(v) || 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) + '%';
            const fmtTime = (months) => {
                if (months === 0) return 'Já chegou! 🎉';
                if (months === null) return 'Mais de 100 anos';
                const y = Math.floor(months / 12), m = months % 12;
                const parts = [];
                if (y) parts.push(y + (y === 1 ? ' ano' : ' anos'));
                if (m) parts.push(m + (m === 1 ? ' mês' : ' meses'));
                return parts.join(' e ');
            };

            Alpine.data('simCompound', (cfg) => ({
                cfg, fmtPct,
                initial: 1000,
                monthly: cfg.contribution,
                years: 10,
                rate: cfg.cdi,
                presets: [
                    { label: 'Poupança', rate: cfg.poupanca },
                    { label: 'CDI 100%', rate: cfg.cdi },
                    { label: 'Tesouro IPCA+ 6%', rate: cfg.tesouroIpca },
                ],
                get series() {
                    const r = monthlyRate(this.rate);
                    const n = Math.max(1, Math.min(60, Math.round(Number(this.years) || 1))) * 12;
                    let bal = Number(this.initial) || 0, inv = bal;
                    const pts = [{ bal, inv }];
                    for (let i = 1; i <= n; i++) {
                        bal = bal * (1 + r) + (Number(this.monthly) || 0);
                        inv += Number(this.monthly) || 0;
                        if (i % (n > 240 ? 12 : 3) === 0 || i === n) pts.push({ bal, inv });
                    }
                    return pts;
                },
                get result() {
                    const last = this.series[this.series.length - 1];
                    const years = Math.max(1, Number(this.years) || 1);
                    return {
                        final: last.bal,
                        invested: last.inv,
                        interest: last.bal - last.inv,
                        real: last.bal / Math.pow(1 + cfg.ipca / 100, years),
                    };
                },
                get chart() {
                    const pts = this.series;
                    const max = Math.max(1, ...pts.map((p) => p.bal));
                    const W = 600, H = 220;
                    const x = (i) => (i / Math.max(1, pts.length - 1)) * W;
                    const y = (v) => H - (v / max) * (H - 8);
                    const area = (key) => 'M0,' + H + ' ' + pts.map((p, i) => `L${x(i).toFixed(1)},${y(p[key]).toFixed(1)}`).join(' ') + ` L${W},${H} Z`;
                    return { total: area('bal'), invested: area('inv'), max };
                },
            }));

            Alpine.data('simFire', (cfg) => ({
                cfg, fmtPct,
                cost: cfg.spending || 3000,
                current: cfg.invested,
                contrib: cfg.contribution,
                realReturn: 4,
                swr: 4,
                get target() { return (Number(this.cost) || 0) * 12 / ((Number(this.swr) || 4) / 100); },
                get progress() { return this.target > 0 ? (Number(this.current) || 0) / this.target * 100 : 0; },
                monthsTo(extra = 0) {
                    const r = monthlyRate(this.realReturn);
                    const c = (Number(this.contrib) || 0) + extra;
                    let bal = Number(this.current) || 0;
                    if (bal >= this.target) return 0;
                    for (let m = 1; m <= 1200; m++) {
                        bal = bal * (1 + r) + c;
                        if (bal >= this.target) return m;
                    }
                    return null;
                },
                get months() { return this.monthsTo(0); },
                get timeLabel() { return fmtTime(this.months); },
                yearsWith(extra) { return fmtTime(this.monthsTo(extra)); },
                get message() {
                    const m = this.months;
                    if (m === 0) return 'Parabéns! Pelos números, seus investimentos já podem sustentar seu custo de vida. 🎉';
                    if (m === null) return 'Com esses números a meta fica muito distante — mas pequenos aumentos no aporte mudam tudo. Veja abaixo. 👇';
                    const age = new Date().getFullYear() + Math.ceil(m / 12);
                    return `Mantendo o ritmo, por volta de ${age} você poderia viver dos rendimentos. Cada real a mais encurta o caminho. 💪`;
                },
            }));

            Alpine.data('simOpportunity', (cfg) => ({
                cfg,
                amount: 200,
                label: 'Delivery',
                years: 10,
                rate: cfg.cdi,
                presets: [
                    { icon: '🍔', label: 'Delivery', amount: 200 },
                    { icon: '☕', label: 'Cafezinho', amount: 120 },
                    { icon: '📺', label: 'Assinaturas', amount: 80 },
                    { icon: '🚗', label: 'Corridas de app', amount: 250 },
                    { icon: '🚬', label: 'Cigarro', amount: 300 },
                ],
                get result() {
                    const r = monthlyRate(this.rate);
                    const n = Math.max(1, Math.round(Number(this.years) || 1)) * 12;
                    const a = Number(this.amount) || 0;
                    const final = r > 0 ? a * ((Math.pow(1 + r, n) - 1) / r) : a * n;
                    return { final, paid: a * n };
                },
                get monthsOfLife() {
                    const m = cfg.spending > 0 ? this.result.final / cfg.spending : 0;
                    return m.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + (m >= 1 && m < 2 ? ' mês' : ' meses');
                },
            }));
        });
    </script>
    @endpush
</x-layouts.app>
