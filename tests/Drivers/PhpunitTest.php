<?php

declare(strict_types=1);

it('outputs json for passing tests', function (): void {
    $output = decodeOutput(runWith('phpunit', 'PassingTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2)
        ->and($output['passed'])->toBe(2)
        ->and($output)->not->toHaveKey('failed')
        ->and($output)->not->toHaveKey('errors');
});

it('outputs json for failing tests', function (): void {
    $output = decodeOutput(runWith('phpunit', 'FailingTest'));

    expect($output['result'])->toBe('failed')
        ->and($output['tests'])->toBe(2)
        ->and($output['failed'])->toBe(1)
        ->and($output['failures'])->toHaveCount(1)
        ->and($output['failures'][0]['file'])->toEndWith('FailingTest.php')
        ->and($output['failures'][0]['line'])->toBeGreaterThan(0);
});

it('outputs json for failures in a beforeClass hook', function (): void {
    $output = decodeOutput(runWith('phpunit', 'BeforeClassHookTest'));

    expect($output['result'])->toBe('failed')
        ->and($output['failed'])->toBe(1)
        ->and($output['failures'])->toHaveCount(1)
        ->and($output['failures'][0]['test'])->toEndWith('BeforeClassHookTest::setUpBeforeClass')
        ->and($output['failures'][0]['file'])->toEndWith('BeforeClassHookTest.php')
        ->and($output['failures'][0]['line'])->toBeGreaterThan(0)
        ->and($output['failures'][0]['message'])->toContain('Failure inside the beforeClass hook');
});

it('outputs json for failures in an afterClass hook', function (): void {
    $output = decodeOutput(runWith('phpunit', 'AfterClassHookTest'));

    expect($output['result'])->toBe('failed')
        ->and($output['failed'])->toBe(1)
        ->and($output['failures'])->toHaveCount(1)
        ->and($output['failures'][0]['test'])->toEndWith('AfterClassHookTest::tearDownAfterClass')
        ->and($output['failures'][0]['file'])->toEndWith('AfterClassHookTest.php')
        ->and($output['failures'][0]['line'])->toBeGreaterThan(0)
        ->and($output['failures'][0]['message'])->toContain('Failure inside the afterClass hook');
});

it('outputs json for errored tests', function (): void {
    $output = decodeOutput(runWith('phpunit', 'ErrorTest'));

    expect($output['result'])->toBe('failed')
        ->and($output['errors'])->toBe(1)
        ->and($output['error_details'])->toHaveCount(1)
        ->and($output['error_details'][0]['message'])->toContain('Something went wrong');
});

it('outputs json for skipped tests', function (): void {
    $output = decodeOutput(runWith('phpunit', 'SkippedTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['skipped'])->toBe(1);
});

it('outputs json for incomplete tests', function (): void {
    $output = decodeOutput(runWith('phpunit', 'IncompleteTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('outputs json for deprecation tests', function (): void {
    $output = decodeOutput(runWith('phpunit', 'DeprecationTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1)
        ->and($output['deprecations'])->toBe(1);
});

it('outputs json for warning tests', function (): void {
    $output = decodeOutput(runWith('phpunit', 'WarningTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1)
        ->and($output['warnings'])->toBe(1);
});

it('outputs json for notice tests', function (): void {
    $output = decodeOutput(runWith('phpunit', 'NoticeTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1)
        ->and($output['notices'])->toBe(1);
});

it('outputs json for risky tests', function (): void {
    $output = decodeOutput(runWith('phpunit', 'RiskyTest'));

    expect($output['tests'])->toBe(1)
        ->and($output['risky'])->toBe(1);
});

it('outputs json for data provider tests', function (): void {
    $output = decodeOutput(runWith('phpunit', 'DataProviderTest'));

    expect($output['tests'])->toBe(3)
        ->and($output['passed'])->toBe(2)
        ->and($output['failed'])->toBe(1)
        ->and($output['failures'])->toHaveCount(1);
});

it('outputs json for dependent tests', function (): void {
    $output = decodeOutput(runWith('phpunit', 'DependsTest'));

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(2)
        ->and($output['passed'])->toBe(2);
});

it('outputs json for tests with unexpected output', function (): void {
    $process = runWith('phpunit', 'UnexpectedOutputTest');
    $raw = $process->getOutput();

    $jsonStart = strpos($raw, '{');
    $output = json_decode(substr($raw, (int) $jsonStart), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($output['result'])->toBe('passed')
        ->and($output['tests'])->toBe(1);
});

it('outputs json for multiple failures and errors', function (): void {
    $output = decodeOutput(runWith('phpunit', 'MultipleFailuresTest'));

    expect($output['result'])->toBe('failed')
        ->and($output['tests'])->toBe(4)
        ->and($output['passed'])->toBe(1)
        ->and($output['failed'])->toBe(2)
        ->and($output['errors'])->toBe(1)
        ->and($output['failures'])->toHaveCount(2)
        ->and($output['error_details'])->toHaveCount(1);
});

it('reports the failing line and omits the trace when it would only repeat file and line', function (): void {
    $output = decodeOutput(runWith('phpunit', 'MultipleFailuresTest'));

    expect($output['failures'][0]['line'])->toBe(18)
        ->and($output['failures'][0])->not->toHaveKey('trace')
        ->and($output['failures'][1]['line'])->toBe(23)
        ->and($output['failures'][1])->not->toHaveKey('trace')
        ->and($output['error_details'][0]['line'])->toBe(28)
        ->and($output['error_details'][0])->not->toHaveKey('trace');
});

it('includes a stack trace when the failure happens inside a shared helper', function (): void {
    $output = decodeOutput(runWith('phpunit', 'SharedHelperTest'));

    expect($output['failures'][0]['file'])->toEndWith('SharedHelperTest.php')
        ->and($output['failures'][0]['line'])->toBe(14)
        ->and($output['failures'][0]['trace'])->toHaveCount(2)
        ->and(normalizePath($output['failures'][0]['trace'][0]))->toEndWith('Support/ChecksTotals.php:13')
        ->and($output['failures'][0]['trace'][1])->toEndWith('SharedHelperTest.php:14');
});

it('drops vendor frames from the stack trace but keeps the first frame', function (): void {
    $output = decodeOutput(runWith('phpunit', 'VendorFramesTest'));

    expect($output['failures'][0]['line'])->toBe(24)
        ->and(array_map(normalizePath(...), $output['failures'][0]['trace']))->each->not->toContain('/vendor/')
        ->and($output['failures'][0]['trace'])->toHaveCount(5)
        ->and($output['error_details'][0]['line'])->toBe(30)
        ->and($output['error_details'][0]['trace'])->toHaveCount(2)
        ->and(normalizePath($output['error_details'][0]['trace'][0]))->toContain('/vendor/laravel/framework/')
        ->and($output['error_details'][0]['trace'][1])->toEndWith('VendorFramesTest.php:30');
});

it('limits stack traces to five frames', function (): void {
    $output = decodeOutput(runWith('phpunit', 'DeepStackTest'));

    expect($output['failures'][0]['line'])->toBe(19)
        ->and($output['failures'][0]['trace'])->toHaveCount(5)
        ->and($output['failures'][0]['trace'][0])->toEndWith('DeepStackTest.php:19');
});

it('outputs normal phpunit output when no agent is detected', function (): void {
    $process = runWith('phpunit', 'PassingTest', withAgent: false);

    expect($process->getOutput())->not->toContain('"result"')
        ->and($process->getOutput())->toContain('OK');
});
