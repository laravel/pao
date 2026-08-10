<?php

declare(strict_types=1);

use Laravel\Pao\Drivers\Paratest\WrapperRunner;
use PHPUnit\TestRunner\TestResult\TestResult;

function supportsDeprecationTriggers(): bool
{
    return method_exists(TestResult::class, 'numberOfSelfDeprecations');
}

/**
 * @param  array<non-empty-string, mixed>  $overrides
 */
function makeTestResult(array $overrides = []): TestResult
{
    $arguments = [];

    foreach ((new ReflectionMethod(TestResult::class, '__construct'))->getParameters() as $parameter) {
        $name = $parameter->getName();
        $type = $parameter->getType();

        $arguments[$name] = array_key_exists($name, $overrides)
            ? $overrides[$name]
            : ($type instanceof ReflectionNamedType && $type->getName() === 'int' ? 0 : []);
    }

    if (supportsDeprecationTriggers() && ! array_key_exists('numberOfDeprecationsByTrigger', $overrides)) {
        $arguments['numberOfDeprecationsByTrigger'] = ['self' => 0, 'direct' => 0, 'indirect' => 0, 'unknown' => 0];
    }

    return new TestResult(...$arguments);
}

/**
 * @param  list<TestResult>  $workerResults
 */
function mergeWorkerResults(TestResult $sum, array $workerResults): TestResult
{
    $files = [];

    foreach ($workerResults as $index => $workerResult) {
        $path = sys_get_temp_dir().'/pao-test-result-'.getmypid().'-'.$index.'.txt';

        file_put_contents($path, serialize($workerResult));

        $files[] = new SplFileInfo($path);
    }

    $runner = (new ReflectionClass(WrapperRunner::class))->newInstanceWithoutConstructor();

    $method = new ReflectionMethod(WrapperRunner::class, 'mergeTestResults');

    try {
        /** @var TestResult $merged */
        $merged = $method->invoke($runner, $sum, $files);
    } finally {
        foreach ($files as $file) {
            @unlink($file->getPathname());
        }
    }

    return $merged;
}

it('merges worker results on every supported phpunit version', function (): void {
    $merged = mergeWorkerResults(
        makeTestResult(['numberOfTests' => 1, 'numberOfTestsRun' => 1, 'numberOfAssertions' => 2]),
        [makeTestResult(['numberOfTests' => 1, 'numberOfTestsRun' => 3, 'numberOfAssertions' => 5])],
    );

    expect($merged->numberOfTestsRun())->toBe(4)
        ->and($merged->numberOfAssertions())->toBe(7);
});

it('sums deprecation trigger counts on phpunit 13.3 and later', function (): void {
    $merged = mergeWorkerResults(
        makeTestResult([
            'numberOfDeprecationsByTrigger' => ['self' => 1, 'direct' => 2, 'indirect' => 3, 'unknown' => 4],
            'retriedTests' => ['first test' => 2],
        ]),
        [
            makeTestResult([
                'numberOfDeprecationsByTrigger' => ['self' => 10, 'direct' => 20, 'indirect' => 30, 'unknown' => 40],
                'retriedTests' => ['second test' => 3],
            ]),
        ],
    );

    expect($merged->numberOfSelfDeprecations())->toBe(11)
        ->and($merged->numberOfDirectDeprecations())->toBe(22)
        ->and($merged->numberOfIndirectDeprecations())->toBe(33)
        ->and($merged->numberOfDeprecationsWithUnknownTrigger())->toBe(44)
        ->and($merged->retriedTests())->toBe(['first test' => 2, 'second test' => 3]);
})->skip(fn (): bool => ! supportsDeprecationTriggers(), 'Requires PHPUnit >= 13.3.0.');

it('omits the deprecation summary before phpunit 13.3', function (): void {
    $method = new ReflectionMethod(WrapperRunner::class, 'mergeDeprecationSummary');

    $runner = (new ReflectionClass(WrapperRunner::class))->newInstanceWithoutConstructor();

    expect($method->invoke($runner, makeTestResult(), makeTestResult()))->toBe([]);
})->skip(fn (): bool => supportsDeprecationTriggers(), 'Requires PHPUnit < 13.3.0.');
