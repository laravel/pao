<?php

declare(strict_types=1);

namespace Laravel\Pao\Drivers\Pest;

use Laravel\Pao\Drivers\Concerns\ProfileCollector;
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
        return 'pest';
    }

    public function start(): void
    {
        $this->registerNullFilter();
        $this->startTimer();
        $this->saveStdout();
        $this->silenceStdout();

        /** @var list<string> $argv */
        $argv = $_SERVER['argv'] ?? [];

        if (in_array('--parallel', $argv, true)) {
            ProfileCollector::startTimerFromNanoseconds(hrtime(true));
            ProfileCollector::executionStarted();
        } else {
            $this->registerProfileSubscriber();
        }
    }
}
