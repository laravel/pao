<?php

declare(strict_types=1);

it('outputs json for passing tests', function (): void {
    $output = decodeOutput(runWith('paratest', 'PassingTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2)
        ->and($output['passed'])->toBe(2)
        ->and($output)->not->toHaveKey('failed')
        ->and($output)->not->toHaveKey('errors')
        ->and($output)->not->toHaveKey('raw');
});

it('outputs json for failing tests', function (): void {
    $output = decodeOutput(runWith('paratest', 'FailingTest'));

    expect($output['result'])->toBe('failed')
        ->and($output['tests'])->toBe(2)
        ->and($output['failed'])->toBe(1)
        ->and($output['failures'])->toHaveCount(1)
        ->and($output['failures'][0]['file'])->toEndWith('FailingTest.php')
        ->and($output['failures'][0]['line'])->toBeGreaterThan(0);
});

it('outputs json for errored tests', function (): void {
    $output = decodeOutput(runWith('paratest', 'ErrorTest'));

    expect($output['result'])->toBe('failed')
        ->and($output['errors'])->toBe(1)
        ->and($output['error_details'])->toHaveCount(1)
        ->and($output['error_details'][0]['message'])->toContain('Something went wrong');
});

it('outputs json for skipped tests', function (): void {
    $output = decodeOutput(runWith('paratest', 'SkippedTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['skipped'])->toBe(1);
});

it('outputs json for incomplete tests', function (): void {
    $output = decodeOutput(runWith('paratest', 'IncompleteTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('outputs json for deprecation tests', function (): void {
    $output = decodeOutput(runWith('paratest', 'DeprecationTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1)
        ->and($output['deprecations'])->toBe(1)
        ->and($output['deprecation_details'])->toHaveCount(1)
        ->and($output['deprecation_details'][0]['file'])->toEndWith('DeprecationTest.php')
        ->and($output['deprecation_details'][0]['message'])->toBe('This function is deprecated');
});

it('outputs json for warning tests', function (): void {
    $output = decodeOutput(runWith('paratest', 'WarningTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('outputs json for notice tests', function (): void {
    $output = decodeOutput(runWith('paratest', 'NoticeTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('outputs json for risky tests', function (): void {
    $output = decodeOutput(runWith('paratest', 'RiskyTest'));

    expect($output['tests'])->toBe(1);
});

it('outputs json for data provider tests', function (): void {
    $output = decodeOutput(runWith('paratest', 'DataProviderTest'));

    expect($output['tests'])->toBe(3)
        ->and($output['passed'])->toBe(2)
        ->and($output['failed'])->toBe(1)
        ->and($output['failures'])->toHaveCount(1);
});

it('outputs json for dependent tests', function (): void {
    $output = decodeOutput(runWith('paratest', 'DependsTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2)
        ->and($output['passed'])->toBe(2);
});

it('outputs json for tests with unexpected output', function (): void {
    $output = decodeOutput(runWith('paratest', 'UnexpectedOutputTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('outputs json for multiple failures and errors', function (): void {
    $output = decodeOutput(runWith('paratest', 'MultipleFailuresTest'));

    expect($output['result'])->toBe('failed')
        ->and($output['tests'])->toBe(4)
        ->and($output['passed'])->toBe(1)
        ->and($output['failed'])->toBe(2)
        ->and($output['errors'])->toBe(1)
        ->and($output['failures'])->toHaveCount(2)
        ->and($output['error_details'])->toHaveCount(1);
});

it('merges issues reported by multiple workers', function (): void {
    $output = decodeOutput(runWith('paratest', 'Deprecation|Notice|Warning', extraArgs: ['--processes', '3']));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(3)
        ->and($output['passed'])->toBe(3)
        ->and($output['deprecations'])->toBe(1)
        ->and($output['warnings'])->toBe(1)
        ->and($output['notices'])->toBe(1)
        ->and($output['deprecation_details'][0]['file'])->toEndWith('DeprecationTest.php')
        ->and($output['warning_details'][0]['file'])->toEndWith('WarningTest.php')
        ->and($output['notice_details'][0]['file'])->toEndWith('NoticeTest.php');
});

it('merges test outcomes reported by multiple workers', function (): void {
    $filter = 'RiskyTest|SkippedTest|IncompleteTest|FailingTest';

    $output = decodeOutput(runWith('paratest', $filter, extraArgs: ['--processes', '4']));

    expect($output['result'])->toBe('failed')
        ->and($output['tests'])->toBe(5)
        ->and($output['passed'])->toBe(3)
        ->and($output['failed'])->toBe(1)
        ->and($output['skipped'])->toBe(1)
        ->and($output['incomplete'])->toBe(1)
        ->and($output['risky'])->toBe(1)
        ->and($output['failures'][0]['file'])->toEndWith('FailingTest.php');
});

it('outputs normal paratest output when no agent is detected', function (): void {
    $process = runWith('paratest', 'PassingTest', withAgent: false);

    expect($process->getOutput())->not->toContain('"result"')
        ->and($process->getOutput())->toContain('OK');
});
