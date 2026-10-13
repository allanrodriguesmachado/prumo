<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $cats = Category::withCount('transactions')->orderBy('archived')->orderBy('name')->get();

        return view('categories.index', ['income' => $cats->where('type', 'income'), 'expense' => $cats->where('type', 'expense')]);
    }

    public function create(Request $request)
    {
        return view('categories.form', ['category' => new Category(['type' => $request->query('tipo', 'expense'), 'icon' => '🏷️', 'color' => '#0f766e', 'group' => 'wants'])]);
    }

    public function store(Request $request)
    {
        Category::create($this->validated($request));

        return redirect()->route('categories.index')->with('success', 'Categoria criada.');
    }

    public function edit(Category $category)
    {
        return view('categories.form', ['category' => $category]);
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request));

        return redirect()->route('categories.index')->with('success', 'Categoria atualizada.');
    }

    public function destroy(Category $category)
    {
        if ($category->transactions()->exists()) {
            $category->update(['archived' => true]);

            return back()->with('success', 'Categoria em uso — foi arquivada.');
        }
        $category->delete();

        return back()->with('success', 'Categoria excluída.');
    }

    private function validated(Request $request): array
    {
        $request->merge(['monthly_budget' => $request->filled('monthly_budget') ? parse_money($request->input('monthly_budget')) : null]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'icon' => ['required', 'string', 'max:16'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'group' => ['required', Rule::in(array_keys(Category::GROUPS))],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['archived'] = $request->boolean('archived');
        if ($data['type'] === 'income') {
            $data['monthly_budget'] = null;
        }

        return $data;
    }
}
