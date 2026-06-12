<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;

final class AbortedRunTest extends TestCase
{
    public function test_aborts_the_run_before_it_finishes(): void
    {
        $this->assertTrue(true);

        exit(0);
    }
}
