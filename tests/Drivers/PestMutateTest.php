<?php

declare(strict_types=1);

const MUTATE_FIXTURE = 'tests/Fixtures/Mutate';
const MUTATE_EMPTY_FIXTURE = 'tests/Fixtures/Mutate/empty';

it('outputs structured json for a mutation run', function (): void {
    $output = decodeOutput(runMutate(MUTATE_FIXTURE));

    expect($output['tool'])->toBe('pest-mutate')
        ->and($output['mutations'])->toBeGreaterThan(0)
        ->and($output['untested'])->toBeGreaterThan(0)
        ->and($output['tested'])->toBeGreaterThan(0)
        ->and($output['score'])->toBeGreaterThan(0)
        ->and($output['duration_ms'])->toBeInt()
        ->and($output['duration_ms'])->toBeGreaterThanOrEqual(0)
        ->and((float) $output['score'])->toBe(round($output['tested'] / $output['mutations'] * 100, 2));

    expect($output)->not->toHaveKey('uncovered')
        ->and($output)->not->toHaveKey('timeout')
        ->and($output)->not->toHaveKey('pending');
});

it('includes untested mutation diff details as structured plain text', function (): void {
    $output = decodeOutput(runMutate(MUTATE_FIXTURE));

    expect($output['untested_details'])->toHaveCount($output['untested']);

    foreach ($output['untested_details'] as $detail) {
        expect($detail['file'])->toEndWith('Calculator.php')
            ->and($detail['line'])->toBeInt()
            ->and($detail['mutator'])->toBeString();
    }

    $diffs = array_column($output['untested_details'], 'diff');
    $comparison = array_values(array_filter($diffs, fn (array $diff): bool => str_contains((string) $diff['original'], 'return $number')));

    expect($comparison)->not->toBeEmpty();

    foreach ($comparison as $diff) {
        expect($diff)->toHaveKeys(['original', 'mutated'])
            ->and($diff['original'])->toBe('return $number > 0;')
            ->and($diff['mutated'])->toContain('return $number')
            ->and($diff['original'].$diff['mutated'])->not->toContain('\\>')
            ->and($diff['original'].$diff['mutated'])->not->toContain('<fg');
    }
});

it('reports no mutations created when nothing is mutable', function (): void {
    $output = decodeOutput(runMutate(MUTATE_EMPTY_FIXTURE));

    expect($output['tool'])->toBe('pest-mutate')
        ->and($output['mutations'])->toBe(0)
        ->and($output['score'])->toBe(0)
        ->and($output)->not->toHaveKey('untested_details');
});

it('emits a single pest-mutate document without normal pest output', function (): void {
    $raw = cleanOutput(runMutate(MUTATE_FIXTURE)->getOutput());

    expect(substr_count($raw, '"tool":"pest-mutate"'))->toBe(1)
        ->and($raw)->not->toContain('"tool":"pest"');
});

it('does not affect output when no agent is detected', function (): void {
    $raw = cleanOutput(runMutate(MUTATE_FIXTURE, withAgent: false)->getOutput());

    expect($raw)->not->toContain('"tool":"pest-mutate"');
});

it('rotutes a parallel mutation run to the pest-mutate driver', function (): void {
    $output = decodeOutput(runMutate(MUTATE_FIXTURE, extraArgs: ['--parallel']));

    expect($output['tool'])->toBe('pest-mutate')
        ->and($output)->toHaveKey('mutations');
});
