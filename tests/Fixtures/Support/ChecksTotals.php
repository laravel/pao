<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support;

use PHPUnit\Framework\Assert;

final class ChecksTotals
{
    public static function assertTotal(string $expected, string $actual): void
    {
        Assert::assertSame($expected, $actual);
    }
}
