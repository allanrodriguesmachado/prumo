<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_split_always_sums_exactly(): void
    {
        foreach ([[0.3, 3], [100, 3], [1000, 7], [0.01, 4], [1999.99, 12], [123456.78, 48]] as [$total, $n]) {
            $parts = Money::split($total, $n);
            $this->assertCount($n, $parts);
            $this->assertSame(Money::toCents($total), array_sum(array_map([Money::class, 'toCents'], $parts)), "{$total} em {$n}");
            $this->assertLessThanOrEqual(1 * $n, Money::toCents(max($parts)) - Money::toCents(min($parts)));
        }
        $this->assertSame([0.1, 0.1, 0.1], Money::split(0.3, 3));
    }

    public function test_sum_has_no_float_drift(): void
    {
        $this->assertSame(0.3, Money::sum([0.1, 0.2]));
        $this->assertSame(1000000.01, Money::sum(array_merge(array_fill(0, 100000, 10.0), [0.01])));
    }
}
