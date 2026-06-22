<?php

declare(strict_types=1);

namespace Laravel\Pao\Drivers;

use Laravel\Pao\Contracts\Driver;
use Laravel\Pao\Execution;
use Laravel\Pao\UserFilters\CaptureFilter;
use Laravel\Pao\UserFilters\NullFilter;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
abstract class Starter implements Driver
{
    protected function registerNullFilter(): void
    {
        if (! in_array('agent_output_null', stream_get_filters(), true)) {
            stream_filter_register('agent_output_null', NullFilter::class);
        }
    }

    protected function silenceStdout(): void
    {
        if (! in_array('agent_output_capture', stream_get_filters(), true)) {
            stream_filter_register('agent_output_capture', CaptureFilter::class);
        }

        CaptureFilter::reset();

        $execution = Execution::current();

        $execution->filter = stream_filter_append(STDOUT, 'agent_output_capture', STREAM_FILTER_WRITE) ?: null;
    }

    protected function discardStdout(): void
    {
        $this->registerNullFilter();

        $execution = Execution::current();

        $execution->filter = stream_filter_append(STDOUT, 'agent_output_null', STREAM_FILTER_WRITE) ?: null;
    }

    protected function silenceStderr(): void
    {
        stream_filter_append(STDERR, 'agent_output_null', STREAM_FILTER_WRITE);
    }

    protected function saveStdout(): void
    {
        $execution = Execution::current();

        $execution->stdout = fopen('php://stdout', 'w') ?: STDOUT;
    }
}
