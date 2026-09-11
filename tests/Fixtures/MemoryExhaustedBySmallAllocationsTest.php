<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;

final class MemoryExhaustedBySmallAllocationsTest extends TestCase
{
    public function test_passes_before_the_process_dies(): void
    {
        $this->assertTrue(true);
    }

    public function test_exhausts_the_memory_limit_with_small_allocations(): void
    {
        fwrite(STDOUT, "output before the process dies\n");

        ini_set('memory_limit', (string) (memory_get_usage(true) + 8 * 1024 * 1024));

        $list = null;

        for ($i = 0; $i < 400_000; $i++) {
            $list = [$list, 'x'.$i];
        }

        $this->assertNotNull($list);
    }
}
