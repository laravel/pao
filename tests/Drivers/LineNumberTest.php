<?php

declare(strict_types=1);

$pestConfig = 'tests/Fixtures/Pest/phpunit.xml';

it('reports correct line for phpunit assertion failure', function (): void {
    $output = decodeOutput(runWith('phpunit', 'FailingTest::test_it_fails'));

    expect($output['failures'][0]['line'])->toBe(18)
        ->and($output['failures'][0]['file'])->toEndWith('FailingTest.php');
});

it('reports correct line for pest expect() failure', function () use ($pestConfig): void {
    $output = decodeOutput(runWith('pest', 'PestFailingTest', config: $pestConfig));

    expect($output['failures'][0]['line'])->toBe(10)
        ->and($output['failures'][0]['file'])->toEndWith('PestFailingTest.php');
});

it('reports correct line for pest expect() failure via pest --parallel', function () use ($pestConfig): void {
    $output = decodeOutput(runWith('pest', 'PestFailingTest', extraArgs: ['--parallel'], config: $pestConfig));

    expect($output['failures'][0]['line'])->toBe(10)
        ->and($output['failures'][0]['file'])->toEndWith('PestFailingTest.php');
});
