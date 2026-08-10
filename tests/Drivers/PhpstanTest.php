<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('outputs json for clean code', function (): void {
    $process = runPhpstan('tests/Fixtures/Phpstan/clean/phpstan.neon');

    $output = decodeOutput($process);

    expect($output['result'])->toBe('passed')
        ->and($output['errors'])->toBe(0)
        ->and($output)->not->toHaveKey('error_details')
        ->and($output)->not->toHaveKey('general_errors');
});

it('outputs json for code with errors', function (): void {
    $process = runPhpstan('tests/Fixtures/Phpstan/errors/phpstan.neon');

    $output = decodeOutput($process);

    $filePath = array_key_first($output['error_details']);

    expect($output['result'])->toBe('failed')
        ->and($output['errors'])->toBe(2)
        ->and($output['error_details'][$filePath])->toHaveCount(2)
        ->and($output['error_details'][$filePath][0])->toHaveKeys(['line', 'message', 'identifier']);
});

it('includes correct identifiers in error details', function (): void {
    $process = runPhpstan('tests/Fixtures/Phpstan/errors/phpstan.neon');

    $output = decodeOutput($process);

    $identifiers = [];
    foreach ($output['error_details'] as $fileErrors) {
        foreach ($fileErrors as $error) {
            $identifiers[] = $error['identifier'];
        }
    }

    expect($identifiers)->toContain('missingType.return')
        ->and($identifiers)->toContain('method.notFound');
});

it('reports correct file path in error details', function (): void {
    $process = runPhpstan('tests/Fixtures/Phpstan/errors/phpstan.neon');

    $output = decodeOutput($process);

    $filePath = array_key_first($output['error_details']);

    expect($filePath)->toEndWith('HasErrors.php')
        ->and($output['error_details'][$filePath][0]['line'])->toBeGreaterThan(0);
});

it('surfaces raw output when the config file is missing', function (): void {
    $process = runPhpstan('does-not-exist.neon');

    $output = decodeOutput($process);

    expect($output['raw'][0])->toContain('does-not-exist.neon')
        ->and($output['raw'][0])->toContain('does not exist')
        ->and($output)->not->toHaveKey('result')
        ->and($output)->not->toHaveKey('errors');
});

it('surfaces raw output for an invalid option', function (): void {
    $process = runPhpstan('tests/Fixtures/Phpstan/clean/phpstan.neon', extraArgs: ['--totally-bogus-flag']);

    $output = decodeOutput($process);

    expect($output['raw'][0])->toContain('--totally-bogus-flag')
        ->and($output)->not->toHaveKey('result');
});

it('transforms the default analyse command when no command is given', function (): void {
    $process = new Process(
        [PHP_BINARY, dirname(__DIR__, 2).'/vendor/bin/phpstan'],
        dirname(__DIR__, 2).'/tests/Fixtures/Phpstan/clean',
        buildAgentEnvironment(),
    );
    $process->run();

    $output = decodeOutput($process);

    expect($output['result'])->toBe('passed')
        ->and($output['errors'])->toBe(0);
});

it('leaves non-analyse commands untouched', function (): void {
    $command = [PHP_BINARY, 'vendor/bin/phpstan', 'clear-result-cache'];

    $process = new Process($command, dirname(__DIR__, 2), buildAgentEnvironment());
    $process->run();

    $raw = $process->getOutput();

    expect($raw)->not->toContain('"tool":"phpstan"')
        ->and($raw)->not->toContain('"result":"failed"')
        ->and($raw)->toContain('Result cache cleared');
});

it('leaves baseline generation untouched', function (): void {
    $baseline = sys_get_temp_dir().'/pao-test-baseline.neon';

    $process = runPhpstan(
        'tests/Fixtures/Phpstan/errors/phpstan.neon',
        extraArgs: ['--generate-baseline', $baseline],
    );

    $raw = $process->getOutput();

    @unlink($baseline);

    expect($raw)->not->toContain('"tool":"phpstan"')
        ->and($raw)->toContain('Baseline generated');
});

it('leaves baseline generation untouched when the shortcut is glued to its value', function (): void {
    $baseline = sys_get_temp_dir().'/pao-test-glued-baseline.neon';

    $process = runPhpstan(
        'tests/Fixtures/Phpstan/errors/phpstan.neon',
        extraArgs: ['-b'.$baseline],
    );

    $raw = $process->getOutput();

    @unlink($baseline);

    expect($raw)->not->toContain('"tool":"phpstan"')
        ->and($raw)->toContain('Baseline generated');
});

it('leaves the version output untouched for either flag', function (string $flag): void {
    $raw = runPhpstanRaw([$flag])->getOutput();

    expect($raw)->not->toContain('"tool":"phpstan"')
        ->and($raw)->not->toContain('"raw"')
        ->and($raw)->toContain('PHPStan');
})->with(['--version', '-V']);

it('leaves the help output untouched for either flag', function (string $flag): void {
    $raw = runPhpstanRaw([$flag])->getOutput();

    expect($raw)->not->toContain('"tool":"phpstan"')
        ->and($raw)->toContain('Usage:');
})->with(['--help', '-h']);

it('passes through normal output without agent', function (): void {
    $process = runPhpstan('tests/Fixtures/Phpstan/errors/phpstan.neon', withAgent: false);

    $raw = $process->getOutput();

    expect($raw)->not->toContain('"result"')
        ->and($raw)->toContain('Found 2 errors');
});

it('truncates error details when more than 30 errors', function (): void {
    $process = runPhpstan('tests/Fixtures/Phpstan/many-errors/phpstan.neon');

    $output = decodeOutput($process);

    $totalErrors = array_sum(array_map(count(...), $output['error_details']));

    expect($output['result'])->toBe('failed')
        ->and($output['errors'])->toBe(50)
        ->and($totalErrors)->toBe(30)
        ->and($output['truncated'])->toBeTrue()
        ->and($output['hint'])->toBe('Pass -v to see all errors.');
});

it('shows all error details with verbose flag', function (): void {
    $process = runPhpstan('tests/Fixtures/Phpstan/many-errors/phpstan.neon', extraArgs: ['-v']);

    $output = decodeOutput($process);

    $totalErrors = array_sum(array_map(count(...), $output['error_details']));

    expect($output['result'])->toBe('failed')
        ->and($output['errors'])->toBe(50)
        ->and($totalErrors)->toBe(50)
        ->and($output)->not->toHaveKey('truncated')
        ->and($output)->not->toHaveKey('hint');
});

it('outputs json when paths follow the end of options separator', function (): void {
    $output = decodeOutput(runPhpstanRaw([
        'analyse', '--configuration', 'tests/Fixtures/Phpstan/errors/phpstan.neon',
        '--', 'tests/Fixtures/Phpstan/errors/src',
    ]));

    expect($output['result'])->toBe('failed')
        ->and($output['errors'])->toBe(2)
        ->and($output)->not->toHaveKey('raw');
});
