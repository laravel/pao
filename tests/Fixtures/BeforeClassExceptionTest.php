<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BeforeClassExceptionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        throw new RuntimeException('Error inside the beforeClass hook');
    }

    public function test_it_never_runs(): void
    {
        $this->assertTrue(true);
    }
}
