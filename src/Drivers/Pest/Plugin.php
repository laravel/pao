<?php

declare(strict_types=1);

namespace Laravel\Pao\Drivers\Pest;

use Laravel\Pao\Execution;
use Pest\Contracts\Plugins\HandlesArguments;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class Plugin implements HandlesArguments
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
}
