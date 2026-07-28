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

        foreach (['--no-output', '--no-progress'] as $flag) {
            if (! in_array($flag, $arguments, true)) {
                $arguments[] = $flag;
            }
        }

        return $arguments;
    }
}
