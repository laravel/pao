<?php

declare(strict_types=1);

namespace Laravel\Pao\Drivers\Paratest;

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
        return 'paratest';
    }

    public function start(): void
    {
        $this->registerNullFilter();
        $this->startTimer();
        $this->silenceStdout();

        /** @var list<string> $serverArgv */
        $serverArgv = $_SERVER['argv'];

        $argv = $serverArgv;

        $argv[] = '--runner';
        $argv[] = WrapperRunner::class;

        $_SERVER['argv'] = $argv;
    }
}
