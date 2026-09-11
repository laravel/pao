<?php

declare(strict_types=1);

/** @codeCoverageIgnoreStart */

namespace Laravel\Pao;

use Laravel\AgentDetector\AgentDetector;

/** @var array<int, string>|null $argv */
$argv = $_SERVER['argv'] ?? null;

if (! is_array($argv) || $argv === []) {
    return;
}

if (filter_var($_SERVER['PAO_DISABLE'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
    return;
}

$agent = AgentDetector::detect();

if (! $agent->isAgent && ! filter_var($_SERVER['PAO_FORCE'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
    return;
}

if (array_intersect($argv, ['--version', '-V', '--help', '-h', 'worker'])) {
    return;
}

unset($_SERVER['COLLISION_PRINTER']);
$_SERVER['PEST_PARALLEL_NO_OUTPUT'] = '1';

$pid = getmypid();
$reservedMemory = str_repeat(' ', 128 * 1024);

register_shutdown_function(function () use ($pid, &$reservedMemory): void {
    $reservedMemory = null;
    $error = error_get_last();

    if (getmypid() !== $pid) {
        return;
    }

    if (! Execution::running()) {
        return;
    }

    $fatalError = is_array($error) && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true) ? $error : null;

    set_error_handler(static fn (): bool => true);

    try {
        $execution = Execution::current();

        $result = $execution->driver->parse() ?: [];

        if ($fatalError !== null) {
            $result = ['result' => 'failed'] + $result;

            $result['fatal_error'] = [
                'message' => $fatalError['message'],
                'file' => $fatalError['file'],
                'line' => $fatalError['line'],
            ];
        }

        $captured = is_resource($execution->filter) ? trim(UserFilters\CaptureFilter::output()) : '';

        $execution->restoreStdout();

        if ($captured !== '') {
            $captured = OutputCleaner::clean($captured);

            $lines = array_values(array_filter(
                array_map(trim(...), explode("\n", $captured)),
                fn (string $line): bool => $line !== ''
                    && ! preg_match('/^[.st!]+$/', $line)
                    && ! preg_match('/^(Tests:|Duration:|Parallel:|Time:|Generating code coverage)\s/', $line)
                    && ! preg_match('/^(INFO\s+)?No tests found\.?$/i', $line)
                    && ! str_ends_with($line, 'by Sebastian Bergmann and contributors.'),
            ));

            if ($lines !== []) {
                $existing = is_array($result['raw'] ?? null) ? array_values($result['raw']) : [];

                $result['raw'] = [...$existing, ...$lines];
            }
        }

        if ($result !== []) {
            $result = ['tool' => $execution->driver->name()] + $result;

            $execution->writeStdout(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR).PHP_EOL);
        }
    } catch (\Throwable $throwable) {
        if ($fatalError === null) {
            throw $throwable;
        }
    } finally {
        restore_error_handler();
    }
});

Execution::start($agent, $argv);
