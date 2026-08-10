<?php

declare(strict_types=1);

use Laravel\Pao\Drivers\Rector\Starter;

/**
 * @param  array<int, string>  $argv
 * @return array<string, mixed>|null
 */
function rectorParse(string $input, array $argv = ['rector']): ?array
{
    /** @var array<int, string> $originalArgv */
    $originalArgv = $_SERVER['argv'];

    $_SERVER['argv'] = $argv;

    $starter = new Starter;

    $level = ob_get_level();

    try {
        $starter->start();

        echo $input;

        return $starter->parse();
    } finally {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        $_SERVER['argv'] = $originalArgv;
        $GLOBALS['argv'] = $originalArgv;
    }
}

/**
 * @param  array<int, string>  $argv
 * @return array<int, string>
 */
function rectorArgv(array $argv): array
{
    /** @var array<int, string> $originalArgv */
    $originalArgv = $_SERVER['argv'];

    $_SERVER['argv'] = $argv;

    $level = ob_get_level();

    try {
        (new Starter)->start();

        /** @var array<int, string> $transformed */
        $transformed = $_SERVER['argv'];

        return $transformed;
    } finally {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        $_SERVER['argv'] = $originalArgv;
        $GLOBALS['argv'] = $originalArgv;
    }
}

/**
 * @param  array<string, mixed>  $totals
 */
function rectorJson(array $totals): string
{
    return (string) json_encode(['totals' => $totals, 'file_diffs' => []]);
}

it('returns null for empty output', function (): void {
    expect(rectorParse(''))->toBeNull();
});

it('surfaces raw output for invalid json instead of staying silent', function (): void {
    $result = rectorParse('not json');

    expect($result)->not->toBeNull()
        ->and($result['raw'])->toBe(['not json'])
        ->and($result)->not->toHaveKey('result');
});

it('surfaces raw output for json without totals', function (): void {
    $result = rectorParse('{"foo":"bar"}');

    expect($result)->not->toBeNull()
        ->and($result['raw'])->toBe(['{"foo":"bar"}'])
        ->and($result)->not->toHaveKey('result');
});

it('surfaces raw output when totals are not integers', function (): void {
    $json = rectorJson(['changed_files' => 'many', 'errors' => 0]);

    $result = rectorParse($json);

    expect($result)->not->toBeNull()
        ->and($result['raw'])->toBe([$json])
        ->and($result)->not->toHaveKey('result');
});

it('returns passed for no changes and no errors', function (): void {
    $result = rectorParse(rectorJson(['changed_files' => 0, 'errors' => 0]), ['rector', '--dry-run']);

    expect($result)->not->toBeNull()
        ->and($result['result'])->toBe('passed')
        ->and($result['totals'])->toBe(['changed_files' => 0, 'errors' => 0]);
});

it('returns failed when errors are reported', function (): void {
    $result = rectorParse(rectorJson(['changed_files' => 0, 'errors' => 2]));

    expect($result)->not->toBeNull()
        ->and($result['result'])->toBe('failed');
});

it('returns failed for pending changes on a dry run', function (): void {
    $result = rectorParse(rectorJson(['changed_files' => 1, 'errors' => 0]), ['rector', '--dry-run']);

    expect($result)->not->toBeNull()
        ->and($result['result'])->toBe('failed');
});

it('treats the short dry run flag as a dry run', function (): void {
    $result = rectorParse(rectorJson(['changed_files' => 1, 'errors' => 0]), ['rector', '-n']);

    expect($result)->not->toBeNull()
        ->and($result['result'])->toBe('failed');
});

it('returns passed for applied changes outside a dry run', function (): void {
    $result = rectorParse(rectorJson(['changed_files' => 1, 'errors' => 0]));

    expect($result)->not->toBeNull()
        ->and($result['result'])->toBe('passed');
});

it('ignores noise printed before the json payload', function (): void {
    $result = rectorParse('Some bootstrap warning'.PHP_EOL.rectorJson(['changed_files' => 0, 'errors' => 0]));

    expect($result)->not->toBeNull()
        ->and($result['result'])->toBe('passed')
        ->and($result)->not->toHaveKey('raw');
});

it('forces the json output format', function (): void {
    expect(rectorArgv(['rector', 'process']))->toBe(['rector', 'process', '--output-format=json']);
});

it('replaces an output format passed with an equals sign', function (): void {
    expect(rectorArgv(['rector', '--output-format=console']))->toBe(['rector', '--output-format=json']);
});

it('replaces an output format passed as a separate value', function (): void {
    expect(rectorArgv(['rector', '--output-format', 'console', '--dry-run']))
        ->toBe(['rector', '--dry-run', '--output-format=json']);
});
