<?php

declare(strict_types=1);

use Rector\Php53\Rector\FuncCall\DirNameFileConstantToDirConstantRector;

it('outputs json for clean code', function (): void {
    $process = runRector('tests/Fixtures/Rector/clean/rector.php', extraArgs: ['--dry-run']);

    $output = decodeOutput($process);

    expect($output['tool'])->toBe('rector')
        ->and($output['result'])->toBe('passed')
        ->and($output['totals']['changed_files'])->toBe(0)
        ->and($output['totals']['errors'])->toBe(0)
        ->and($output)->not->toHaveKey('file_diffs')
        ->and($output)->not->toHaveKey('errors');
});

it('outputs json for code with changes', function (): void {
    $process = runRector('tests/Fixtures/Rector/changes/rector.php', extraArgs: ['--dry-run']);

    $output = decodeOutput($process);

    expect($output['tool'])->toBe('rector')
        ->and($output['result'])->toBe('failed')
        ->and($output['totals']['changed_files'])->toBe(1)
        ->and($output['totals']['errors'])->toBe(0)
        ->and($output['file_diffs'])->toHaveCount(1)
        ->and($output['file_diffs'][0]['file'])->toEndWith('NeedsChange.php')
        ->and($output['file_diffs'][0]['diff'])->toContain("-        return [dirname(__FILE__), 'change'];")
        ->and($output['file_diffs'][0]['applied_rectors'])->toContain(DirNameFileConstantToDirConstantRector::class)
        ->and($output['changed_files'])->toContain('tests/Fixtures/Rector/changes/src/NeedsChange.php');
});

it('outputs json when paths follow the end of options separator', function (): void {
    $output = decodeOutput(runRectorRaw([
        'process', '--config', 'tests/Fixtures/Rector/changes/rector.php', '--dry-run',
        '--', 'tests/Fixtures/Rector/changes/src',
    ]));

    expect($output['tool'])->toBe('rector')
        ->and($output['result'])->toBe('failed')
        ->and($output['totals']['changed_files'])->toBe(1)
        ->and($output)->not->toHaveKey('raw');
});

it('surfaces fatal errors when the config cannot be loaded', function (): void {
    $output = decodeOutput(runRectorRaw(['process', '--config=does-not-exist.php', '--dry-run']));

    expect($output['tool'])->toBe('rector')
        ->and($output['result'])->toBe('failed')
        ->and($output['fatal_errors'])->toHaveCount(1)
        ->and($output['fatal_errors'][0])->toContain('does-not-exist.php')
        ->and($output)->not->toHaveKey('raw');
});

it('leaves non-process commands untouched', function (): void {
    $raw = runRectorRaw(['list'])->getOutput();

    expect($raw)->not->toContain('"tool":"rector"')
        ->and($raw)->not->toContain('--output-format')
        ->and($raw)->toContain('Available commands');
});

it('passes through normal output without agent', function (): void {
    $process = runRector('tests/Fixtures/Rector/changes/rector.php', withAgent: false, extraArgs: ['--dry-run']);

    $raw = $process->getOutput();

    expect($raw)->not->toContain('"tool"')
        ->and($raw)->toContain('would have been changed');
});
