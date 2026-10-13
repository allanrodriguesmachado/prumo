<?php

namespace App\Support;

/**
 * Operações monetárias em centavos inteiros — evita erros de ponto flutuante
 * (ex.: 0,1 + 0,2) em divisões de parcelas e somas críticas.
 */
final class Money
{
    public static function toCents(float|int|string $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    public static function fromCents(int $cents): float
    {
        return $cents / 100;
    }

    /**
     * Divide um valor em N partes que somam exatamente o total.
     * Os centavos que sobram vão para a primeira parcela.
     *
     * @return float[]
     */
    public static function split(float|int|string $total, int $parts): array
    {
        $parts = max(1, $parts);
        $cents = self::toCents($total);
        $each = intdiv($cents, $parts);
        $rest = $cents - $each * $parts;

        return array_map(fn ($i) => self::fromCents($each + ($i === 0 ? $rest : 0)), range(0, $parts - 1));
    }

    public static function sum(iterable $values): float
    {
        $c = 0;
        foreach ($values as $v) {
            $c += self::toCents($v);
        }

        return self::fromCents($c);
    }
}
