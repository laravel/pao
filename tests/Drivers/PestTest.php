<?php

declare(strict_types=1);

it('outputs json for passing tests', function (): void {
    $output = decodeOutput(runWith('pest', 'PassingTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2)
        ->and($output['passed'])->toBe(2)
        ->and($output)->not->toHaveKey('failed')
        ->and($output)->not->toHaveKey('errors');
});

it('outputs json for failing tests', function (): void {
    $output = decodeOutput(runWith('pest', 'FailingTest'));

    expect($output['result'])->toBe('failed')
        ->and($output['tests'])->toBe(2)
        ->and($output['failed'])->toBe(1)
        ->and($output['failures'])->toHaveCount(1)
        ->and($output['failures'][0]['test'])->toContain('Failing');
});

it('outputs json for errored tests', function (): void {
    $output = decodeOutput(runWith('pest', 'ErrorTest'));

    expect($output['result'])->toBe('failed')
        ->and($output['errors'])->toBe(1)
        ->and($output['error_details'])->toHaveCount(1)
        ->and($output['error_details'][0]['message'])->toContain('Something went wrong');
});

it('outputs json for skipped tests', function (): void {
    $output = decodeOutput(runWith('pest', 'SkippedTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['skipped'])->toBe(1);
});

it('outputs json for incomplete tests', function (): void {
    $output = decodeOutput(runWith('pest', 'IncompleteTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('outputs json for deprecation tests', function (): void {
    $output = decodeOutput(runWith('pest', 'DeprecationTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1)
        ->and($output['deprecations'])->toBe(1);
});

it('outputs json for warning tests', function (): void {
    $output = decodeOutput(runWith('pest', 'WarningTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1)
        ->and($output['warnings'])->toBe(1);
});

it('outputs json for notice tests', function (): void {
    $output = decodeOutput(runWith('pest', 'NoticeTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1)
        ->and($output['notices'])->toBe(1);
});

it('outputs json for risky tests', function (): void {
    $output = decodeOutput(runWith('pest', 'RiskyTest'));

    expect($output['tests'])->toBe(1)
        ->and($output['risky'])->toBe(1);
});

it('outputs failed json when no pest tests match', function (): void {
    $process = runWith('pest', 'definitely no matching test', config: 'tests/Fixtures/Pest/phpunit.xml');

    expect($process->getExitCode())->not->toBe(0);

    $output = decodeOutput($process);

    expect($output['result'])->toBe('failed')
        ->and($output['tests'])->toBe(0)
        ->and($output['passed'])->toBe(0)
        ->and($output['raw'])->toContain('No tests found.');
});

it('outputs json for data provider tests', function (): void {
    $output = decodeOutput(runWith('pest', 'DataProviderTest'));

    expect($output['tests'])->toBe(3)
        ->and($output['passed'])->toBe(2)
        ->and($output['failed'])->toBe(1)
        ->and($output['failures'])->toHaveCount(1);
});

it('outputs json for dependent tests', function (): void {
    $output = decodeOutput(runWith('pest', 'DependsTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2)
        ->and($output['passed'])->toBe(2);
});

it('outputs json for tests with unexpected output', function (): void {
    $output = decodeFromMixedOutput(runWith('pest', 'UnexpectedOutputTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('outputs json for multiple failures and errors', function (): void {
    $output = decodeOutput(runWith('pest', 'MultipleFailuresTest'));

    expect($output['result'])->toBe('failed')
        ->and($output['tests'])->toBe(4)
        ->and($output['passed'])->toBe(1)
        ->and($output['failed'])->toBe(2)
        ->and($output['errors'])->toBe(1)
        ->and($output['failures'])->toHaveCount(2)
        ->and($output['error_details'])->toHaveCount(1);
});

it('outputs normal pest output when no agent is detected', function (): void {
    $process = runWith('pest', 'PassingTest', withAgent: false);

    expect($process->getOutput())->not->toContain('"result"')
        ->and($process->getOutput())->toContain('passed');
});

it('includes stack traces for failures', function (): void {
    $output = decodeOutput(runWith('pest', 'FailingTest'));

    expect($output['failures'][0]['trace'][0])->toEndWith('FailingTest.php:18');
});

it('outputs json when PAO_FORCE is set without an agent', function (): void {
    $output = decodeOutput(runWith('pest', 'PassingTest', withAgent: false, extraEnv: ['PAO_FORCE' => '1']));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2);
});

it('outputs normal pest output when PAO_FORCE is falsy without an agent', function (string $value): void {
    $process = runWith('pest', 'PassingTest', withAgent: false, extraEnv: ['PAO_FORCE' => $value]);

    expect($process->getOutput())->not->toContain('"result"')
        ->and($process->getOutput())->toContain('passed');
})->with(['0', 'false']);

it('does not repeat an output flag the caller already passed', function (string $flag): void {
    $process = runWith('pest', 'PassingTest', extraArgs: [$flag]);

    expect($process->getExitCode())->toBe(0)
        ->and(decodeOutput($process)['result'])->toBe('passed');
})->with(['--no-output', '--no-progress']);
