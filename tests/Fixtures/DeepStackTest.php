<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;

final class DeepStackTest extends TestCase
{
    public function test_it_fails_deep_in_the_stack(): void
    {
        $this->descend(15);
    }

    private function descend(int $depth): void
    {
        if ($depth === 0) {
            $this->assertTrue(false);
        } else {
            $this->descend($depth - 1);
        }
    }
}
