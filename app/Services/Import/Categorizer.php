<?php

namespace App\Services\Import;

use App\Models\Category;
use App\Models\CategoryRule;
use Illuminate\Support\Str;

/**
 * Categorização inteligente: primeiro usa as regras aprendidas com o próprio
 * usuário (cada vez que ele corrige uma categoria o Prumo aprende), depois um
 * dicionário de palavras-chave comuns no Brasil.
 */
class Categorizer
{
    private const KEYWORDS = [
        'Mercado' => ['mercado', 'supermerc', 'atacad', 'assai', 'carrefour', 'pao de acucar', 'hortifruti', 'sacolao', 'acougue', 'padaria', 'extra hiper', 'dia brasil', 'big bompreco', 'zaffari', 'savegnago'],
        'Restaurantes e delivery' => ['ifood', 'rappi', 'restaurante', 'lanchonete', 'burger', 'mcdonald', 'pizza', 'bar ', 'boteco', 'cafe', 'starbucks', 'outback', 'subway', 'habibs', 'ze delivery', 'aiqfome'],
        'Transporte' => ['uber', '99app', '99 pop', '99pop', 'posto', 'combust', 'shell', 'ipiranga', 'petrobras', 'br mania', 'estacion', 'sem parar', 'conectcar', 'veloe', 'metro', 'cptm', 'bilhete unico', 'onibus', 'pedagio', 'cabify'],
        'Assinaturas' => ['netflix', 'spotify', 'disney', 'hbo', 'max.com', 'prime video', 'amazon prime', 'youtube', 'deezer', 'globoplay', 'paramount', 'apple.com', 'icloud', 'google one', 'chatgpt', 'openai', 'claude', 'anthropic', 'crunchyroll', 'xbox', 'playstation', 'gympass', 'wellhub', 'smartfit', 'smart fit'],
        'Contas da casa' => ['enel', 'light', 'cemig', 'copel', 'celesc', 'coelba', 'energia', 'sabesp', 'copasa', 'agua', 'comgas', 'naturgy', 'gas ', 'vivo', 'claro', 'tim ', 'oi fibra', 'internet', 'net servicos', 'condominio'],
        'Moradia' => ['aluguel', 'imobiliaria', 'quintoandar', 'iptu', 'financiamento hab'],
        'Saúde' => ['farmacia', 'drogaria', 'drogasil', 'raia', 'pague menos', 'panvel', 'hospital', 'clinica', 'laboratorio', 'unimed', 'amil', 'bradesco saude', 'hapvida', 'odonto', 'dentista', 'medico', 'psicolog'],
        'Educação' => ['escola', 'faculdade', 'universidade', 'curso', 'udemy', 'alura', 'livraria', 'mensalidade', 'colegio', 'duolingo'],
        'Compras' => ['mercado livre', 'mercadolivre', 'amazon', 'shopee', 'magalu', 'magazine luiza', 'americanas', 'aliexpress', 'shein', 'casas bahia', 'renner', 'riachuelo', 'c&a', 'zara', 'centauro', 'netshoes', 'kabum'],
        'Lazer' => ['cinema', 'ingresso', 'sympla', 'eventim', 'show', 'teatro', 'parque', 'steam', 'clube'],
        'Beleza e cuidados' => ['salao', 'barbearia', 'estetica', 'boticario', 'natura', 'sephora', 'manicure'],
        'Viagens' => ['hotel', 'airbnb', 'booking', 'decolar', 'latam', 'gol linhas', 'azul linhas', 'passagem', '123milhas', 'pousada'],
        'Pets' => ['petz', 'cobasi', 'petshop', 'pet shop', 'veterin'],
        'Impostos e taxas' => ['iof', 'tarifa', 'anuidade', 'ipva', 'darf', 'detran', 'multa', 'taxa'],
        'Dívidas e juros' => ['juros', 'encargos', 'mora', 'rotativo', 'emprestimo', 'parc empr', 'consignado'],
        'Investimentos' => ['aplicacao', 'tesouro', 'corretora', 'xp invest', 'rico', 'clear', 'nuinvest', 'btg', 'cdb'],
        'Salário' => ['salario', 'pagto salario', 'folha', 'vencimentos', 'proventos sal'],
        'Rendimentos' => ['rendimento', 'rend pago', 'dividendo', 'juros sobre capital', 'jcp'],
        'Reembolsos' => ['estorno', 'reembolso', 'devolucao', 'cashback'],
    ];

    public static function normalize(string $text): string
    {
        $t = Str::of($text)->ascii()->lower()->replaceMatches('/\d+[\/\-]\d+/', ' ')->replaceMatches('/[^a-z ]/', ' ')->squish()->toString();

        return $t;
    }

    /** Chave de aprendizado: as 2 primeiras palavras relevantes da descrição. */
    public static function ruleKey(string $text): string
    {
        $stop = ['pix', 'enviado', 'recebido', 'compra', 'pagamento', 'pag', 'cartao', 'debito', 'credito', 'transf', 'transferencia', 'ted', 'doc', 'de', 'da', 'do', 'em', 'para', 'no', 'na', 'boleto', 'deb', 'aut', 'elo', 'visa', 'master'];
        $words = array_values(array_filter(explode(' ', self::normalize($text)), fn ($w) => strlen($w) > 2 && ! in_array($w, $stop)));

        return implode(' ', array_slice($words, 0, 2));
    }

    public function guess(string $description, string $type = 'expense'): ?Category
    {
        $norm = ' '.self::normalize($description).' ';
        $key = self::ruleKey($description);

        if ($key !== '') {
            $rule = CategoryRule::with('category')->where('pattern', $key)->first();
            if ($rule && $rule->category && $rule->category->type === $type) {
                $rule->increment('hits');

                return $rule->category;
            }
        }

        foreach (self::KEYWORDS as $category => $words) {
            foreach ($words as $w) {
                if (str_contains($norm, $w)) {
                    $cat = Category::where('name', $category)->where('type', $type)->first();
                    if ($cat) {
                        return $cat;
                    }
                }
            }
        }

        return null;
    }

    public function learn(string $description, int $categoryId): void
    {
        $key = self::ruleKey($description);
        if ($key === '') {
            return;
        }
        CategoryRule::updateOrCreate(['pattern' => $key], ['category_id' => $categoryId]);
    }
}
