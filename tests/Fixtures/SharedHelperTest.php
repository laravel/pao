<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Support\ChecksTotals;

final class SharedHelperTest extends TestCase
{
    public function test_it_fails_inside_a_shared_helper(): void
    {
        ChecksTotals::assertTotal('USD 17.99', 'USD 17.49');
    }
}
