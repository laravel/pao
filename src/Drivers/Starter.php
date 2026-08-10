<?php

declare(strict_types=1);

namespace Laravel\Pao\Drivers;

use Laravel\Pao\Contracts\Driver;
use Laravel\Pao\Execution;
use Laravel\Pao\UserFilters\CaptureFilter;
use Laravel\Pao\UserFilters\NullFilter;
use Laravel\Pao\UserFilters\StderrCaptureFilter;

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

    protected function captureStderr(): void
    {
        if (! in_array('agent_output_stderr_capture', stream_get_filters(), true)) {
            stream_filter_register('agent_output_stderr_capture', StderrCaptureFilter::class);
        }

        StderrCaptureFilter::reset();

        stream_filter_append(STDERR, 'agent_output_stderr_capture', STREAM_FILTER_WRITE);
    }

    protected function saveStdout(): void
    {
        $execution = Execution::current();

        $execution->stdout = fopen('php://stdout', 'w') ?: STDOUT;
    }

    /**
     * @param  array<int, string>  $argv
     */
    protected function commandName(array $argv): ?string
    {
        foreach (array_slice($argv, 1) as $arg) {
            if (! str_starts_with($arg, '-')) {
                return $arg;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $argv
     * @return array<int, string>
     */
    protected function addOption(array $argv, string $option): array
    {
        $separator = array_search('--', $argv, true);

        if (! is_int($separator)) {
            $argv[] = $option;

            return $argv;
        }

        array_splice($argv, $separator, 0, [$option]);

        return $argv;
    }
}
