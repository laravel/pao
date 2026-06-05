<?php

declare(strict_types=1);

$extraArgs = ['--parallel'];

it('outputs json for passing tests', function () use ($extraArgs): void {
    $output = decodeOutput(runWith('pest', 'PassingTest', extraArgs: $extraArgs));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2)
        ->and($output['passed'])->toBe(2)
        ->and($output)->not->toHaveKey('failed')
        ->and($output)->not->toHaveKey('errors')
        ->and($output)->not->toHaveKey('raw');
});

it('outputs json for failing tests', function () use ($extraArgs): void {
    $output = decodeOutput(runWith('pest', 'FailingTest', extraArgs: $extraArgs));

    expect($output['result'])->toBe('failed')
        ->and($output['tests'])->toBe(2)
        ->and($output['failed'])->toBe(1)
        ->and($output['failures'])->toHaveCount(1)
        ->and($output['failures'][0]['test'])->toContain('Failing');
});

it('outputs json for errored tests', function () use ($extraArgs): void {
    $output = decodeOutput(runWith('pest', 'ErrorTest', extraArgs: $extraArgs));

    expect($output['result'])->toBe('failed')
        ->and($output['errors'])->toBe(1)
        ->and($output['error_details'])->toHaveCount(1)
        ->and($output['error_details'][0]['message'])->toContain('Something went wrong');
});

it('outputs json for skipped tests', function () use ($extraArgs): void {
    $output = decodeOutput(runWith('pest', 'SkippedTest', extraArgs: $extraArgs));

    expect($output['result'])->toBe('passed')
        ->and($output['skipped'])->toBe(1);
});

it('outputs json for incomplete tests', function () use ($extraArgs): void {
    $output = decodeOutput(runWith('pest', 'IncompleteTest', extraArgs: $extraArgs));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('outputs json for deprecation tests', function () use ($extraArgs): void {
    $output = decodeOutput(runWith('pest', 'DeprecationTest', extraArgs: $extraArgs));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('outputs json for warning tests', function () use ($extraArgs): void {
    $output = decodeOutput(runWith('pest', 'WarningTest', extraArgs: $extraArgs));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('outputs json for notice tests', function () use ($extraArgs): void {
    $output = decodeOutput(runWith('pest', 'NoticeTest', extraArgs: $extraArgs));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('outputs json for risky tests', function () use ($extraArgs): void {
    $output = decodeOutput(runWith('pest', 'RiskyTest', extraArgs: $extraArgs));

    expect($output['tests'])->toBe(1);
});

it('outputs json for data provider tests', function () use ($extraArgs): void {
    $output = decodeOutput(runWith('pest', 'DataProviderTest', extraArgs: $extraArgs));

    expect($output['tests'])->toBe(3)
        ->and($output['passed'])->toBe(2)
        ->and($output['failed'])->toBe(1)
        ->and($output['failures'])->toHaveCount(1);
});

it('outputs json for dependent tests', function () use ($extraArgs): void {
    $output = decodeOutput(runWith('pest', 'DependsTest', extraArgs: $extraArgs));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2)
        ->and($output['passed'])->toBe(2);
});

it('outputs json for tests with unexpected output', function () use ($extraArgs): void {
    $output = decodeOutput(runWith('pest', 'UnexpectedOutputTest', extraArgs: $extraArgs));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('outputs json for multiple failures and errors', function () use ($extraArgs): void {
    $output = decodeOutput(runWith('pest', 'MultipleFailuresTest', extraArgs: $extraArgs));

    expect($output['result'])->toBe('failed')
        ->and($output['tests'])->toBe(4)
        ->and($output['passed'])->toBe(1)
        ->and($output['failed'])->toBe(2)
        ->and($output['errors'])->toBe(1)
        ->and($output['failures'])->toHaveCount(2)
        ->and($output['error_details'])->toHaveCount(1);
});

it('outputs normal pest output when no agent is detected', function () use ($extraArgs): void {
    $process = runWith('pest', 'PassingTest', withAgent: false, extraArgs: $extraArgs);

    expect($process->getOutput())->not->toContain('"result"');
});

it('outputs failed json when parallel coverage cannot start without a driver', function (): void {
    $process = runWith('pest', 'PassingTest', extraArgs: ['--parallel', '--coverage', '--min=100'], extraEnv: [
        'XDEBUG_MODE' => 'coverage',
    ]);

    expect($process->getExitCode())->not->toBe(0);

    $output = decodeOutput($process);

    expect($output['result'])->toBe('failed')
        ->and($output['tests'])->toBe(0)
        ->and($output['passed'])->toBe(0)
        ->and($output['raw'])->toContain('ERROR No code coverage driver is available.');
});
