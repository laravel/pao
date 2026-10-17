<?php

declare(strict_types=1);

dataset('test result runners', [
    'phpunit' => ['phpunit', []],
    'paratest' => ['paratest', ['--processes=2']],
    'pest' => ['pest', []],
    'pest parallel' => ['pest', ['--parallel', '--processes=2']],
]);

it('matches the exit status after Pest coverage policies', function (): void {
    foreach ([[], ['--parallel', '--processes=2']] as $arguments) {
        foreach (['--min', '--exactly'] as $policy) {
            foreach ([0, 100] as $threshold) {
                $extraArgs = [
                    ...$arguments,
                    '--coverage',
                    '--coverage-filter',
                    'src',
                    $policy.'='.$threshold,
                ];
                $native = runWith('pest', 'PassingTest', withAgent: false, extraArgs: $extraArgs, extraEnv: ['PARATEST' => false]);
                $process = runWith('pest', 'PassingTest', extraArgs: $extraArgs, extraEnv: ['PARATEST' => false]);
                $output = decodeOutput($process);

                expect(cleanOutput($native->getOutput()))->toContain('Total: 0.0 %')
                    ->and($native->getExitCode())->toBe($threshold === 0 ? 0 : 1)
                    ->and($process->getExitCode())->toBe($native->getExitCode())
                    ->and($output['result'])->toBe($process->isSuccessful() ? 'passed' : 'failed')
                    ->and($output['tests'])->toBe(2)
                    ->and($output['passed'])->toBe(2);
            }
        }
    }
});

it('matches the exit status with and without strict outcome policies', function (string $binary, array $arguments, string $filter, string $flag): void {
    foreach ([false, true] as $strict) {
        $extraArgs = $strict ? [...$arguments, $flag] : $arguments;
        $native = runWith($binary, $filter, withAgent: false, extraArgs: $extraArgs);
        $process = runWith($binary, $filter, extraArgs: $extraArgs);
        $output = decodeOutput($process);

        expect($native->getExitCode())->toBe($strict ? 1 : 0)
            ->and($process->getExitCode())->toBe($native->getExitCode())
            ->and($output['result'])->toBe($process->isSuccessful() ? 'passed' : 'failed');
    }
})->with('test result runners')->with([
    'warning' => ['WarningTest', '--fail-on-warning'],
    'notice' => ['NoticeTest', '--fail-on-notice'],
    'deprecation' => ['DeprecationTest', '--fail-on-deprecation'],
    'skipped' => ['SkippedTest', '--fail-on-skipped'],
    'incomplete' => ['IncompleteTest', '--fail-on-incomplete'],
    'risky' => ['RiskyTest', '--fail-on-risky'],
]);

it('includes diagnostics for errors in class hooks', function (string $binary, array $arguments, string $filter, string $method, string $message): void {
    $native = runWith($binary, $filter, withAgent: false, extraArgs: $arguments);
    $process = runWith($binary, $filter, extraArgs: $arguments);
    $output = decodeOutput($process);

    expect($native->isSuccessful())->toBeFalse()
        ->and($process->getExitCode())->toBe($native->getExitCode())
        ->and($output['result'])->toBe('failed')
        ->and($output['errors'])->toBe(1)
        ->and($output['error_details'])->toHaveCount(1)
        ->and($output['error_details'][0]['test'])->toEndWith($filter.'::'.$method)
        ->and($output['error_details'][0]['file'])->toEndWith($filter.'.php')
        ->and($output['error_details'][0]['line'])->toBe(14)
        ->and($output['error_details'][0]['message'])->toBe($message);
})->with('test result runners')->with([
    'before class' => ['BeforeClassExceptionTest', 'setUpBeforeClass', 'Error inside the beforeClass hook'],
    'after class' => ['AfterClassExceptionTest', 'tearDownAfterClass', 'Error inside the afterClass hook'],
]);
