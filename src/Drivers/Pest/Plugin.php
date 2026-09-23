<?php

declare(strict_types=1);

namespace Laravel\Pao\Drivers\Pest;

use Laravel\Pao\Execution;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Contracts\Plugins\ObservesExitCode;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class Plugin implements HandlesArguments, ObservesExitCode
{
    public function __construct()
    {
        //
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function handleArguments(array $arguments): array
    {
        if (! Execution::running()) {
            return $arguments;
        }

        if (! in_array('--no-output', $arguments, true)) {
            $arguments[] = '--no-output';
        }

        if (! in_array('--no-progress', $arguments, true)) {
            $arguments[] = '--no-progress';
        }

        return $arguments;
    }

    public function observeExitCode(int $exitCode): void
    {
        if (Execution::running()) {
            $driver = Execution::current()->driver;

            if ($driver instanceof Starter) {
                $driver->recordExitCode($exitCode);
            }
        }
    }
}
