<?php

use App\Models\Household;
use Illuminate\Support\Facades\Auth;

if (! function_exists('brl')) {
    function brl(float|int|string|null $value, bool $sign = false): string
    {
        $v = (float) $value;
        $s = 'R$ '.number_format(abs($v), 2, ',', '.');
        if ($v < 0) {
            return '-'.$s;
        }

        return $sign && $v > 0 ? '+'.$s : $s;
    }
}

if (! function_exists('pct')) {
    function pct(float|int|null $value, int $decimals = 1): string
    {
        return number_format((float) $value, $decimals, ',', '.').'%';
    }
}

if (! function_exists('num')) {
    function num(float|int|null $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }
}

if (! function_exists('household')) {
    function household(): ?Household
    {
        return Auth::user()?->currentHousehold;
    }
}

if (! function_exists('can_write')) {
    /** O usuário logado pode alterar dados da carteira ativa? (falso para "somente leitura") */
    function can_write(): bool
    {
        static $cache = [];
        $user = Auth::user();
        if (! $user || ! $user->currentHousehold) {
            return false;
        }
        $key = $user->id.':'.$user->current_household_id;

        return $cache[$key] ??= $user->currentHousehold->canWrite($user);
    }
}

if (! function_exists('parse_money')) {
    /** Aceita "1.234,56", "1234.56", "R$ 1.234,56". */
    function parse_money(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $v = preg_replace('/[^\d,.\-]/', '', (string) $value);
        if (str_contains($v, ',')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        }

        return (float) $v;
    }
}

if (! function_exists('parse_decimal')) {
    /** Quantidades em formato brasileiro: "1.000" = mil, "0,5" = meio. */
    function parse_decimal(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $v = preg_replace('/[^\d,.\-]/', '', (string) $value);
        if (str_contains($v, ',')) {
            return (float) str_replace(',', '.', str_replace('.', '', $v));
        }
        // só pontos: "1.000" (milhar) vs "0.5" (decimal)
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) {
            return (float) str_replace('.', '', $v);
        }

        return (float) $v;
    }
}

if (! function_exists('month_name')) {
    function month_name(\Carbon\CarbonInterface $d, bool $short = false): string
    {
        $names = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $n = $names[$d->month - 1];

        return $short ? mb_substr($n, 0, 3) : $n;
    }
}
