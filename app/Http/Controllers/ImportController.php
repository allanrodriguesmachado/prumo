<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Import;
use App\Services\Import\ImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ImportController extends Controller
{
    public function index()
    {
        return view('imports.index', [
            'accounts' => Account::where('archived', false)->get(),
            'cards' => CreditCard::where('archived', false)->get(),
            'history' => Import::with('user')->latest()->take(10)->get(),
        ]);
    }

    public function preview(Request $request, ImportService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:ofx,qfx,csv,txt,xlsx'],
            'method' => ['required', 'string'],
        ], [], ['file' => 'arquivo', 'method' => 'conta ou cartão']);

        $method = (string) $request->input('method');
        $target = str_starts_with($method, 'card:')
            ? ['credit_card_id' => CreditCard::findOrFail((int) substr($method, 5))->id]
            : ['account_id' => Account::findOrFail((int) substr($method, 8))->id];
        $preview = $service->preview($request->file('file'), $target);
        if (! $preview['rows']) {
            return back()->withErrors(['file' => 'Não encontramos lançamentos neste arquivo. Use OFX ou um CSV com colunas de data, descrição e valor.']);
        }
        session(['import_preview' => $preview]);

        return view('imports.preview', [
            'preview' => $preview,
            'method' => $request->input('method'),
            'isShared' => $request->boolean('is_shared'),
            'categories' => Category::where('archived', false)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ImportService $service)
    {
        $preview = session('import_preview');
        abort_unless($preview, 419, 'A pré-visualização expirou. Envie o arquivo novamente.');
        $hid = household()->id;
        $data = $request->validate([
            'method' => ['required', 'string'],
            'rows' => ['array'],
            'rows.*.selected' => ['nullable'],
            'rows.*.category_id' => ['nullable', Rule::exists('categories', 'id')->where('household_id', $hid)],
        ]);

        $target = ['is_shared' => $request->boolean('is_shared')];
        if (str_starts_with($data['method'], 'card:')) {
            $target['credit_card_id'] = CreditCard::findOrFail((int) substr($data['method'], 5))->id;
        } else {
            $target['account_id'] = Account::findOrFail((int) substr($data['method'], 8))->id;
        }

        $rows = [];
        foreach ($preview['rows'] as $i => $r) {
            $rows[] = $r + [
                'selected' => ! empty($data['rows'][$i]['selected']),
            ];
            $rows[$i]['category_id'] = $data['rows'][$i]['category_id'] ?? $r['category_id'];
        }
        $import = $service->commit($rows, $target, $preview['filename'], $preview['format']);
        session()->forget('import_preview');

        return redirect()->route('transactions.index')->with('success', "{$import->imported} lançamento(s) importado(s), {$import->reconciled} conciliado(s) com lançamentos que você já tinha e {$import->skipped} ignorado(s). O Prumo aprendeu suas categorias para as próximas importações. 🧠");
    }
}
