# Convenções das telas (Blade) do Prumo

Projeto Laravel 13 + Blade + Tailwind CSS v4 + Alpine.js 3 + Chart.js 4. Idioma: **português do Brasil** em toda a interface.

## Layout
Toda página autenticada usa o componente de layout:
```blade
<x-layouts.app title="Título curto da página">
    <x-page-header title="Título" subtitle="Explicação curta">
        <x-slot:actions> <a href="..." class="btn-primary">+ Novo</a> </x-slot:actions>
    </x-page-header>
    ...conteúdo...
</x-layouts.app>
```
Mensagens flash (`session('success')`) e erros de validação já são exibidos pelo layout — não repita.

## Componentes disponíveis (resources/views/components)
- `<x-page-header title subtitle>` com slot `actions`
- `<x-stat label value hint tone icon />` — tone: slate|green|red|brand|amber|violet. `hint` aceita HTML.
- `<x-empty icon title text>` + slot com botão
- `<x-money-input name value label help required />` — campo de dinheiro com máscara R$ (envia "1.234,56"; o backend usa `parse_money()`)
- `<x-field-error name="campo" />`
- `<x-month-nav :month="$month" route="nome.da.rota" :params="[]" />` — navega por `?mes=Y-m`
- `<x-modal name="id" title="...">` — abrir com `@click="$dispatch('open-modal', 'id')"`
- `<x-progress :value="75" />` (cor automática por %, ou `color="bg-emerald-500"`)

## Classes CSS prontas (resources/css/app.css)
`card`, `card-pad`, `card-title`, `muted`, `btn-primary`, `btn-secondary`, `btn-ghost`, `btn-danger`, `btn-sm`,
`label`, `input`, `input-error`, `help`, `error`, `checkbox`, `badge-green|red|amber|slate|violet|sky`,
`table` (use dentro de `<div class="overflow-x-auto">`), `progress`, `money-pos`, `money-neg`, `tabular`, `font-display`.
Paleta (mesma do Laravel), definida em `resources/css/app.css`:
- Marca `brand-50 … brand-950`: vermelho Laravel (`brand-600` = `#F53003`, `brand-500` = `#FF4433`, `brand-950` = `#1D0002`). Use para botões principais, links, foco e destaques, **nunca para valores em dinheiro positivos**.
- `slate-*` foi remapeado para os neutros quentes do Laravel (`#1b1b18`, `#706f6c`, `#a1a09a`, `#e3e3e0`…), então `text-slate-*`/`bg-slate-*` já seguem a paleta.
- Valores: positivo = `emerald`, negativo = `rose`, neutro = `slate-900`. Alertas: `amber`; informação: `sky`.
- Cartões de destaque: `.hero` (escuro com brilho vermelho) ou `.hero-red` (chamada para ação). Fonte: Instrument Sans.
Cards: `<div class="card card-pad">`. Grid: `grid gap-4 sm:grid-cols-2 lg:grid-cols-4`.

## Helpers PHP globais
- `brl($valor, $sinal = false)` → "R$ 1.234,56"
- `pct($valor, $casas = 1)` → "12,5%"
- `num($valor, $casas = 2)` → "1.234,56"
- `month_name($carbon, $curto = false)` → "outubro" / "out"
- `household()` → carteira ativa (`->isCouple()`, `->members`, `->name`, `->setting('chave')`)
- `parse_money()` (backend)

## Gráficos (Chart.js declarativo)
```blade
<div class="h-64"><canvas data-chart='@json([
    'type' => 'bar',
    'data' => ['labels' => [...], 'datasets' => [['label' => 'Receitas', 'data' => [...], 'backgroundColor' => '#10b981', 'borderRadius' => 6]]],
])'></canvas></div>
```
Por padrão eixos e tooltips são formatados em R$. Passe `'money' => false` no array raiz para números puros.
Tipos registrados: bar, line, doughnut. Para doughnut use `'options' => ['cutout' => '70%', 'plugins' => ['legend' => ['display' => false]]]`.

## Formulários
- Sempre `@csrf`, `@method('PUT')` na edição. Use `old('campo', $model->campo)`.
- Checkboxes booleanos: `<input type="checkbox" name="is_shared" value="1" class="checkbox" @checked(old('is_shared', $model->is_shared))>`.
- "Meio de pagamento" (conta OU cartão) usa um único `<select name="method">` com opções `account:{id}` e `card:{id}`.
- Para carteira de casal (`household()->isCouple()`), mostrar o checkbox **"Despesa compartilhada do casal"** (`is_shared`).
- Botão excluir: form com `@method('DELETE')` e `onsubmit="return confirm('...')"`.

## Estilo
- Interface moderna, limpa, amigável e acolhedora (o público inclui pessoas endividadas — tom sem julgamento, encorajador).
- Mobile-first: tudo deve funcionar em 375px. Tabelas largas: `overflow-x-auto`; em mobile prefira listas/cards.
- Use emojis com moderação como ícones de categoria/estado.
- Valores: receitas `money-pos`, despesas `money-neg`, sempre `tabular`.
- Não use bibliotecas novas nem CDN; só o que já existe (Alpine, Chart.js, Tailwind).
- NÃO rode docker, npm, composer nem artisan — apenas escreva os arquivos .blade.php pedidos.

## Responsividade
- O `<main>` é um container (`@container`). Grades de várias colunas dentro das telas usam **container queries** (`@3xl:grid-cols-2`, `@4xl:grid-cols-3`, `@5xl:grid-cols-3`) e não `lg:`, porque a barra lateral ocupa 16rem a partir de `lg` e a área útil é menor que a janela.
- Indicadores (`<x-stat>`) ficam em `.stat-grid` (2 colunas no celular; o último ímpar ocupa a linha toda) e adaptam o tamanho do valor à largura do próprio cartão.
- Valores em dinheiro (`.tabular` em `dd`/`td`/`span`/`b` e `.font-display.tabular`) não quebram linha.
- Tabelas largas sempre dentro de `.overflow-x-auto`. Grades têm `minmax(0,1fr)` por padrão, então nada alarga a página.
- Celular (< `lg`): barra de navegação inferior (Início, Lançamentos, botão Lançar, Consultor, Menu); o menu lateral abre como gaveta e fecha com Esc. O conteúdo tem `pb-28` para não ficar sob a barra.
