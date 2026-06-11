<?php

declare(strict_types=1);

namespace Laravel\Pao\Drivers\Phpunit;

use Laravel\Pao\Drivers\Concerns\TestResultParsable;
use Laravel\Pao\Drivers\Starter as BaseStarter;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class Starter extends BaseStarter
{
    use TestResultParsable;

    public function name(): string
    {
        return 'phpunit';
    }

    public function start(): void
    {
        $this->registerNullFilter();
        $this->startTimer();
        $this->registerExecutionFinishedSubscriber();
        $this->registerProfileSubscriber();

        /** @var list<string> $serverArgv */
        $serverArgv = $_SERVER['argv'];

        $argv = $serverArgv;

        if (! in_array('--no-output', $argv, true)) {
            $argv[] = '--no-output';
        }

        $_SERVER['argv'] = $argv;
    }
}
