<?php

declare(strict_types=1);

it('does not break tests that read argv', function (): void {
    $output = decodeOutput(runWith('phpunit', 'ArgvTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2);
});

it('does not break tests that fwrite to stdout', function (): void {
    $process = runWith('phpunit', 'StdoutWriteTest');
    $raw = $process->getOutput();

    $jsonStart = strpos($raw, '{');
    $output = json_decode(substr($raw, (int) $jsonStart), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2);
});

it('does not break tests that register custom stream filters', function (): void {
    $output = decodeOutput(runWith('phpunit', 'StreamFilterTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2);
});

it('preserves user junit file when user also passes log-junit', function (): void {
    $userJunitFile = sys_get_temp_dir().'/user-junit-'.bin2hex(random_bytes(4)).'.xml';

    $process = runWith('phpunit', 'ExistingJunitTest', extraArgs: ['--log-junit', $userJunitFile]);

    expect($process->getExitCode())->toBe(0);

    expect(file_exists($userJunitFile))->toBeTrue();

    $xml = simplexml_load_file($userJunitFile);
    expect($xml)->not->toBeFalse();

    @unlink($userJunitFile);
});

it('works correctly when no agent is detected and does not affect output', function (): void {
    $process = runWith('phpunit', 'PassingTest', withAgent: false);

    $output = $process->getOutput();

    expect($output)->toContain('OK')
        ->and($output)->not->toContain('"result"')
        ->and($output)->not->toContain('agent-output');
});

it('does not break when running with process isolation', function (): void {
    $output = decodeOutput(runWith('phpunit', 'PassingTest', extraArgs: ['--process-isolation']));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2);
});

it('does not break tests that use ob_start', function (): void {
    $output = decodeOutput(runWith('phpunit', 'OutputBufferingTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(3);
});

it('does not break tests that modify environment variables', function (): void {
    $output = decodeOutput(runWith('phpunit', 'EnvironmentTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(3);
});

it('does not break tests that set custom error handlers', function (): void {
    $output = decodeOutput(runWith('phpunit', 'ErrorHandlerTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2);
});

it('does not break tests with RunInSeparateProcess attribute', function (): void {
    $output = decodeOutput(runWith('phpunit', 'ProcessIsolationTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2);
});

it('does not break tests with BackupGlobals', function (): void {
    $output = decodeOutput(runWith('phpunit', 'BackupGlobalsTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2);
});

it('does not break with large test output', function (): void {
    $process = runWith('phpunit', 'LargeOutputTest');
    $raw = $process->getOutput();

    $jsonStart = strpos($raw, '{');
    $output = json_decode(substr($raw, (int) $jsonStart), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('handles unicode in test names and assertion messages', function (): void {
    $output = decodeOutput(runWith('phpunit', 'UnicodeTest'));

    expect($output['result'])->toBe('failed')
        ->and($output['tests'])->toBe(3)
        ->and($output['passed'])->toBe(2)
        ->and($output['failed'])->toBe(1)
        ->and($output['failures'][0]['message'])->toContain('café');
});

it('does not break tests with DoesNotPerformAssertions attribute', function (): void {
    $output = decodeOutput(runWith('phpunit', 'DoesNotPerformAssertionsTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('does not break tests that register shutdown functions', function (): void {
    $output = decodeOutput(runWith('phpunit', 'ShutdownFunctionTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('does not emit a result when the run is aborted before it finishes', function (): void {
    $process = runWith('phpunit', 'AbortedRunTest');

    expect($process->getOutput())->not->toContain('"tool"')
        ->and($process->getOutput())->not->toContain('"result"');
});

it('adds a summary to a run that dies from a fatal error and keeps its exit code', function (string $binary, string $filter, string $message): void {
    $phpArgs = ['-d', 'display_errors=0', '-d', 'log_errors=0'];

    $withoutPao = runWith($binary, $filter, extraEnv: ['PAO_DISABLE' => '1'], phpArgs: $phpArgs);
    $withPao = runWith($binary, $filter, phpArgs: $phpArgs);

    expect($withPao->getExitCode())->toBe($withoutPao->getExitCode());

    $output = cleanOutput($withPao->getOutput());

    expect(preg_match('/^\{"tool":.*$/m', $output, $matches))->toBe(1);

    $summary = json_decode($matches[0], associative: true, flags: JSON_THROW_ON_ERROR);
    $rest = trim((string) preg_replace('/^\{"tool":.*\n?/m', '', $output));

    expect($summary['tool'])->toBe($binary)
        ->and($summary['result'])->toBe('failed')
        ->and($summary['fatal_error']['message'])->toContain($message)
        ->and(normalizePath($summary['fatal_error']['file']))->toContain('tests/Fixtures/'.$filter.'.php')
        ->and(cleanOutput($withoutPao->getOutput()))->toMatch('/'.preg_quote($rest, '/').'\z/');
})->with([
    'pest, compile error' => ['pest', 'RedeclaredFunctionTest', 'Cannot redeclare'],
    'pest, memory exhaustion' => ['pest', 'MemoryExhaustionTest', 'Allowed memory size'],
    'phpunit, compile error' => ['phpunit', 'RedeclaredFunctionTest', 'Cannot redeclare'],
    'phpunit, memory exhaustion' => ['phpunit', 'MemoryExhaustionTest', 'Allowed memory size'],
    'phpunit, memory exhausted by small allocations' => ['phpunit', 'MemoryExhaustedBySmallAllocationsTest', 'Allowed memory size'],
]);

it('reports a fatal error with what the test printed when no memory is left', function (): void {
    $process = runWith('pest', 'MemoryExhaustedBySmallAllocationsTest', phpArgs: ['-d', 'display_errors=0', '-d', 'log_errors=0']);
    $output = cleanOutput($process->getOutput());

    expect($process->getExitCode())->not->toBe(0)
        ->and(preg_match('/^\{"tool":.*$/m', $output, $matches))->toBe(1);

    $summary = json_decode($matches[0], associative: true, flags: JSON_THROW_ON_ERROR);

    expect($summary['result'])->toBe('failed')
        ->and($summary['fatal_error']['message'])->toContain('Allowed memory size')
        ->and($summary['raw'])->toContain('output before the process dies');
});

it('does not break tests that spawn child processes', function (): void {
    $output = decodeOutput(runWith('phpunit', 'ExecTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2);
});

it('handles large number of tests correctly', function (): void {
    $output = decodeOutput(runWith('phpunit', 'ManyTestsTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(100)
        ->and($output['passed'])->toBe(100);
});
