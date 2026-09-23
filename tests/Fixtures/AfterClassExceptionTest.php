<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AfterClassExceptionTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        throw new RuntimeException('Error inside the afterClass hook');
    }

    public function test_it_passes(): void
    {
        $this->assertTrue(true);
    }
}
