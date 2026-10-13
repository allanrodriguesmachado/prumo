import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import {
    Chart, ArcElement, BarElement, LineElement, PointElement, BarController, LineController, DoughnutController,
    CategoryScale, LinearScale, Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(ArcElement, BarElement, LineElement, PointElement, BarController, LineController, DoughnutController,
    CategoryScale, LinearScale, Tooltip, Legend, Filler);

const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
Chart.defaults.font.family = "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.color = '#706f6c';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.maintainAspectRatio = false;

export const brl = (v) => (Number(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
window.brl = brl;

const moneyTicks = { callback: (v) => brl(v).replace(',00', '') };

/**
 * Gráficos declarativos: <canvas data-chart='{"type":"bar", "data": {...}}'>
 * opções extras: "money": true formata eixos/tooltip em reais.
 */
function mountCharts(root = document) {
    root.querySelectorAll('canvas[data-chart]').forEach((el) => {
        if (el._chart) return;
        const cfg = JSON.parse(el.dataset.chart);
        const money = cfg.money !== false;
        cfg.options = cfg.options || {};
        if (money) {
            cfg.options.plugins = cfg.options.plugins || {};
            cfg.options.plugins.tooltip = {
                callbacks: { label: (ctx) => `${ctx.dataset.label || ctx.label}: ${brl(ctx.parsed.y ?? ctx.parsed)}` },
            };
            if (cfg.type !== 'doughnut') {
                cfg.options.scales = cfg.options.scales || {};
                cfg.options.scales.y = Object.assign({ ticks: moneyTicks, grid: { color: '#a1a09a33' }, border: { display: false } }, cfg.options.scales.y || {});
                cfg.options.scales.x = Object.assign({ grid: { display: false }, border: { display: false } }, cfg.options.scales.x || {});
            }
        }
        el._chart = new Chart(el, cfg);
    });
}
window.mountCharts = mountCharts;
document.addEventListener('DOMContentLoaded', () => mountCharts());

/** Campo de dinheiro com máscara pt-BR. */
Alpine.data('money', (initial = '') => ({
    raw: initial === null || initial === '' ? '' : Number(initial).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    get value() {
        return this.raw === '' ? 0 : Number(this.raw.replace(/\./g, '').replace(',', '.')) || 0;
    },
    format(e) {
        let digits = e.target.value.replace(/\D/g, '').replace(/^0+/, '');
        if (!digits) { this.raw = ''; return; }
        digits = digits.padStart(3, '0');
        const n = Number(digits.slice(0, -2) + '.' + digits.slice(-2));
        this.raw = n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
}));

/** Chat do consultor com IA. */
Alpine.data('aiChat', (conversationId = null, sendUrl = '') => ({
    conversationId, input: '', loading: false, error: '',
    async send(text = null) {
        const message = (text ?? this.input).trim();
        if (!message || this.loading) return;
        this.input = ''; this.error = ''; this.loading = true;
        const list = this.$refs.messages;
        list.insertAdjacentHTML('beforeend', `<div class="flex justify-end"><div class="chat-user">${message.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]))}</div></div>`);
        this.$nextTick(() => list.scrollTop = list.scrollHeight);
        try {
            const res = await fetch(sendUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ message, conversation_id: this.conversationId }),
            });
            if (!res.ok) throw new Error(res.status === 429 ? 'Muitas mensagens seguidas. Aguarde um minuto.' : 'Não foi possível obter resposta.');
            const data = await res.json();
            this.conversationId = data.conversation_id;
            history.replaceState({}, '', data.url);
            list.insertAdjacentHTML('beforeend', `<div class="flex gap-3"><div class="ai-avatar">P</div><div class="chat-ai prose-chat">${data.html}<div class="mt-2 text-[11px] text-slate-400">via ${data.provider}</div></div></div>`);
        } catch (e) {
            this.error = e.message;
        } finally {
            this.loading = false;
            this.$nextTick(() => list.scrollTop = list.scrollHeight);
        }
    },
}));

window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.start();

/* Tema claro/escuro: 'light' | 'dark' | 'system' salvo no navegador. */
window.prumoTheme = {
    get() { try { return localStorage.getItem('prumo-theme') || 'system'; } catch { return 'system'; } },
    set(v) {
        try { localStorage.setItem('prumo-theme', v); } catch {}
        this.apply();
    },
    apply() {
        const v = this.get();
        const dark = v === 'dark' || (v === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    },
};
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => window.prumoTheme.apply());

/* Feedback de envio: desabilita o botão e mostra carregamento (evita envio duplo). */
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (e.defaultPrevented || form.dataset.noLoading !== undefined || form.method.toLowerCase() === 'get') return;
    const btn = e.submitter || form.querySelector('button:not([type=button])');
    setTimeout(() => {
        if (e.defaultPrevented) return;
        form.querySelectorAll('button:not([type=button])').forEach((b) => { b.disabled = b !== btn; });
        if (btn) { btn.classList.add('is-loading'); btn.setAttribute('aria-busy', 'true'); }
    }, 0);
});
window.addEventListener('pageshow', () => document.querySelectorAll('.is-loading').forEach((b) => { b.classList.remove('is-loading'); b.removeAttribute('aria-busy'); }));
