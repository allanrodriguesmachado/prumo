<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetOperation;
use App\Services\FinanceAnalytics;
use App\Services\Market\BcbClient;
use App\Services\Market\FixedIncomeCalculator;
use App\Services\Market\QuoteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvestmentController extends Controller
{
    public function index(BcbClient $bcb, QuoteService $quotes)
    {
        $assets = Asset::with(['operations', 'user'])->orderBy('class')->orderBy('name')->get();
        $positions = $assets->mapWithKeys(fn ($a) => [$a->id => $a->position()]);
        $total = $positions->sum('value');
        $cost = $positions->sum('cost');
        $income = $positions->sum('income');

        $byClass = $assets->groupBy('class')->map(fn ($g, $class) => [
            'label' => Asset::CLASSES[$class][0] ?? $class,
            'color' => Asset::CLASSES[$class][1] ?? '#64748b',
            'value' => $g->sum(fn ($a) => $positions[$a->id]['value']),
        ])->sortByDesc('value');

        // rentabilidade comparada ao CDI no mesmo período (desde o primeiro aporte)
        $first = AssetOperation::whereIn('asset_id', $assets->pluck('id'))->where('type', 'buy')->min('date');
        $cdiFactor = null;
        if ($first) {
            $fake = new Asset(['class' => 'fixed_income', 'indexer' => 'cdi', 'rate' => 100]);
            $cdiFactor = app(FixedIncomeCalculator::class)->factor($fake, Carbon::parse($first), now());
        }

        // proventos por mês (últimos 12)
        $dividends = AssetOperation::whereIn('asset_id', $assets->pluck('id'))->whereIn('type', ['dividend', 'jcp', 'yield'])
            ->where('date', '>=', now()->subMonths(11)->startOfMonth())->get()
            ->groupBy(fn ($o) => $o->date->format('Y-m'))->map->sum('total');

        $a = FinanceAnalytics::current();

        return view('investments.index', [
            'assets' => $assets,
            'positions' => $positions,
            'total' => $total,
            'cost' => $cost,
            'income' => $income,
            'gain' => $positions->sum('gain'),
            'byClass' => $byClass,
            'cdiFactor' => $cdiFactor,
            'firstDate' => $first ? Carbon::parse($first) : null,
            'dividends' => $dividends,
            'indicators' => rescue(fn () => $bcb->indicators(), [], false),
            'fx' => rescue(fn () => $quotes->currencies(), [], false),
            'monthlySpending' => $a->averageMonthlySpending(),
            'passiveIncome' => $total * 0.004, // ~0,4% a.m. como referência conservadora
        ]);
    }

    public function create(Request $request)
    {
        return view('investments.form', ['asset' => new Asset(['class' => $request->query('classe', 'stock'), 'indexer' => 'cdi', 'rate' => 100, 'is_shared' => household()->isCouple()])]);
    }

    public function store(Request $request, QuoteService $quotes)
    {
        $data = $this->validated($request);
        $asset = Asset::create($data);

        if ($request->filled('first_amount') || $request->filled('first_quantity')) {
            $this->addOperation($asset, [
                'type' => 'buy',
                'date' => $request->input('first_date', now()->toDateString()),
                'quantity' => $request->input('first_quantity'),
                'price' => $request->input('first_price'),
                'fees' => 0,
                'total' => $request->input('first_amount'),
            ]);
        }
        rescue(fn () => $quotes->refresh($asset), null, false);

        return redirect()->route('investments.show', $asset)->with('success', 'Ativo adicionado à carteira.');
    }

    public function show(Asset $asset)
    {
        $asset->load('operations');

        return view('investments.show', ['asset' => $asset, 'position' => $asset->position()]);
    }

    public function edit(Asset $asset)
    {
        return view('investments.form', ['asset' => $asset]);
    }

    public function update(Request $request, Asset $asset)
    {
        $asset->update($this->validated($request));

        return redirect()->route('investments.show', $asset)->with('success', 'Ativo atualizado.');
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();

        return redirect()->route('investments.index')->with('success', 'Ativo removido.');
    }

    public function storeOperation(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(AssetOperation::TYPES))],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'quantity' => ['nullable'],
            'price' => ['nullable'],
            'fees' => ['nullable'],
            'total' => ['nullable'],
        ]);
        $this->addOperation($asset, $data);

        return back()->with('success', 'Operação registrada.');
    }

    public function destroyOperation(AssetOperation $operation)
    {
        abort_unless(Asset::whereKey($operation->asset_id)->exists(), 404);
        $operation->delete();

        return back()->with('success', 'Operação excluída.');
    }

    public function refresh(QuoteService $quotes, BcbClient $bcb)
    {
        foreach (['cdi', 'selic', 'ipca', 'savings'] as $code) {
            rescue(fn () => $bcb->sync($code), null, false);
        }
        $ok = 0; $fail = [];
        foreach (Asset::whereNotNull('ticker')->get() as $a) {
            if ($a->isMarketPriced()) {
                $quotes->refresh($a) ? $ok++ : $fail[] = $a->ticker;
            }
        }
        $msg = "Indicadores do Banco Central e {$ok} cotação(ões) atualizados.";
        if ($fail) {
            $msg .= ' Não encontrados: '.implode(', ', $fail).(config('prumo.brapi_token') ? '' : ' (configure BRAPI_TOKEN gratuito para todos os tickers da B3).');
        }

        return back()->with('success', $msg);
    }

    public function tickers(Request $request, QuoteService $quotes)
    {
        return response()->json($quotes->searchTickers((string) $request->query('q')));
    }

    public function simulators(BcbClient $bcb)
    {
        $a = FinanceAnalytics::current();
        $ind = rescue(fn () => $bcb->indicators(), [], false);

        return view('investments.simulators', [
            'cdi' => $ind['cdi']['value'] ?? 10.5,
            'selic' => $ind['selic']['value'] ?? 10.5,
            'ipca' => $ind['ipca_12m']['value'] ?? 4.5,
            'monthlySpending' => $a->averageMonthlySpending() ?: 3000,
            'monthlyIncome' => $a->monthlyIncome(),
            'invested' => Asset::with('operations')->get()->sum(fn ($x) => $x->position()['value']),
        ]);
    }

    private function addOperation(Asset $asset, array $d): void
    {
        $qty = parse_decimal($d['quantity'] ?? 0);
        $price = parse_decimal($d['price'] ?? 0);
        $fees = parse_money($d['fees'] ?? 0);
        $total = parse_money($d['total'] ?? 0);

        $valueOnly = in_array($asset->pricing(), ['fixed', 'manual']) || ! in_array($d['type'], ['buy', 'sell']);
        if ($valueOnly) {
            // renda fixa, fundos e proventos: basta informar o valor
            $total = $total ?: $qty * $price;
            $qty = in_array($d['type'], ['buy', 'sell']) ? ($qty ?: 1) : 0;
            $price = $qty ? $total / $qty : 0;
        } else {
            $total = $total ?: $qty * $price + ($d['type'] === 'buy' ? $fees : -$fees);
            $price = $price ?: ($qty > 0 ? $total / $qty : 0);
        }
        if ($total <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['total' => 'Informe o valor da operação (quantidade × preço ou valor total).']);
        }

        $asset->operations()->create(['type' => $d['type'], 'date' => $d['date'], 'quantity' => $qty, 'price' => $price, 'fees' => $fees, 'total' => round($total, 2)]);
        // ativos manuais guardam em last_price o valor atual TOTAL da posição
        if ($asset->pricing() === 'manual' && $asset->last_price !== null && in_array($d['type'], ['buy', 'sell'])) {
            $asset->update(['last_price' => max(0, $asset->last_price + ($d['type'] === 'buy' ? $total : -$total))]);
        }
    }

    private function validated(Request $request): array
    {
        if ($request->filled('rate')) {
            $request->merge(['rate' => parse_money($request->input('rate'))]);
        }
        if ($request->filled('last_price')) {
            $request->merge(['last_price' => parse_money($request->input('last_price'))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'class' => ['required', Rule::in(array_keys(Asset::CLASSES))],
            'ticker' => ['nullable', 'string', 'max:30'],
            'indexer' => ['nullable', Rule::in(array_keys(Asset::INDEXERS))],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'maturity_date' => ['nullable', 'date'],
            'institution' => ['nullable', 'string', 'max:120'],
            'last_price' => ['nullable', 'numeric', 'min:0'],
        ], [], ['name' => 'nome', 'class' => 'classe']);
        $data['ticker'] = isset($data['ticker']) ? strtoupper(trim($data['ticker'])) : null;
        $data['is_private'] = $request->boolean('is_private');
        $data['is_shared'] = ! $data['is_private'] && $request->boolean('is_shared');
        if (Asset::CLASSES[$data['class']][2] !== 'fixed') {
            $data['indexer'] = null;
        }
        if ($data['class'] === 'savings') {
            $data['indexer'] = 'savings';
        }

        return $data;
    }
}
