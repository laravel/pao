<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;

final class AbortedRunTest extends TestCase
{
    public function test_aborts_the_run_before_it_finishes(): void
    {
        $this->assertTrue(true);

        // Simulate a run that never reaches the TestRunner\ExecutionFinished
        // event (e.g. a fatal error or an interrupted process). The shutdown
        // function still fires, but no completed test result should be emitted.
        exit(0);
    }
}
