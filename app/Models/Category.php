<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToHousehold;

    public const GROUPS = [
        'needs' => 'Essencial',
        'wants' => 'Estilo de vida',
        'savings' => 'Futuro (poupar/quitar)',
    ];

    protected $fillable = ['household_id', 'name', 'type', 'icon', 'color', 'group', 'monthly_budget', 'archived'];

    protected function casts(): array
    {
        return ['monthly_budget' => 'decimal:2', 'archived' => 'boolean'];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public static function defaults(): array
    {
        return [
            // receitas
            ['Salário', 'income', '💼', '#059669', 'needs'],
            ['Renda extra', 'income', '✨', '#10b981', 'needs'],
            ['Freelance', 'income', '🧑‍💻', '#14b8a6', 'needs'],
            ['Rendimentos', 'income', '📈', '#0ea5e9', 'savings'],
            ['Reembolsos', 'income', '↩️', '#64748b', 'needs'],
            ['Outras receitas', 'income', '➕', '#94a3b8', 'needs'],
            // despesas essenciais
            ['Moradia', 'expense', '🏠', '#0f766e', 'needs'],
            ['Contas da casa', 'expense', '💡', '#0891b2', 'needs'],
            ['Mercado', 'expense', '🛒', '#16a34a', 'needs'],
            ['Transporte', 'expense', '🚌', '#2563eb', 'needs'],
            ['Saúde', 'expense', '🩺', '#dc2626', 'needs'],
            ['Educação', 'expense', '📚', '#7c3aed', 'needs'],
            ['Filhos', 'expense', '🧸', '#db2777', 'needs'],
            ['Pets', 'expense', '🐾', '#a16207', 'needs'],
            ['Impostos e taxas', 'expense', '🧾', '#475569', 'needs'],
            // estilo de vida
            ['Restaurantes e delivery', 'expense', '🍔', '#ea580c', 'wants'],
            ['Lazer', 'expense', '🎉', '#d946ef', 'wants'],
            ['Assinaturas', 'expense', '📺', '#9333ea', 'wants'],
            ['Compras', 'expense', '🛍️', '#f59e0b', 'wants'],
            ['Beleza e cuidados', 'expense', '💅', '#ec4899', 'wants'],
            ['Viagens', 'expense', '✈️', '#06b6d4', 'wants'],
            ['Presentes', 'expense', '🎁', '#f43f5e', 'wants'],
            // futuro
            ['Dívidas e juros', 'expense', '⛓️', '#b91c1c', 'savings'],
            ['Metas e reservas', 'expense', '🎯', '#0d9488', 'savings'],
            ['Investimentos', 'expense', '🌱', '#15803d', 'savings'],
            ['Outros', 'expense', '📦', '#64748b', 'wants'],
        ];
    }
}
