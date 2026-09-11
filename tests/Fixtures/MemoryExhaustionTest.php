<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;

final class MemoryExhaustionTest extends TestCase
{
    public function test_passes_before_the_process_dies(): void
    {
        $this->assertTrue(true);
    }

    public function test_exhausts_the_memory_limit(): void
    {
        ini_set('memory_limit', (string) (memory_get_usage(true) + 16 * 1024 * 1024));

        $chunks = [];

        for ($i = 0; $i < 64; $i++) {
            $chunks[] = str_repeat('x', 1024 * 1024);
        }

        $this->assertCount(64, $chunks);
    }
}
