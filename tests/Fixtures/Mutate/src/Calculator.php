<?php

declare(strict_types=1);

final class MutateCalculator
{
    public function isPositive(int $number): bool
    {
        return $number > 0;
    }

    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}
