<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;

final class RedeclaredFunctionTest extends TestCase
{
    public function test_passes_before_the_process_dies(): void
    {
        $this->assertTrue(true);
    }

    public function test_dies_with_a_compile_error(): void
    {
        eval('function strlen() {}');
    }
}
