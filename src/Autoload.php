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

if (isset($_SERVER['PAO_DISABLE'])) {
    return;
}

$agent = AgentDetector::detect();

if (! $agent->isAgent && ! isset($_SERVER['PAO_FORCE'])) {
    return;
}

if (array_intersect($argv, ['--version', '--help', '-h', 'worker'])) {
    return;
}

unset($_SERVER['COLLISION_PRINTER']);
$_SERVER['PEST_PARALLEL_NO_OUTPUT'] = '1';

register_shutdown_function(function (): void {
    if (! Execution::running()) {
        return;
    }

    $execution = Execution::current();

    $result = $execution->driver->parse() ?: [];

    $captured = trim(UserFilters\CaptureFilter::output());

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

            if (($result['result'] ?? null) === 'passed'
                && in_array('ERROR No code coverage driver is available.', $lines, true)) {
                $result['result'] = 'failed';
            }
        }
    }

    if ($result !== []) {
        $result = ['tool' => $execution->driver->name()] + $result;

        fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR).PHP_EOL);
    }
});

Execution::start($agent, $argv);
