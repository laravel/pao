<?php

declare(strict_types=1);

require_once __DIR__.'/src/Calculator.php';

covers(MutateCalculator::class);

it('detects a positive number', function (): void {
    expect((new MutateCalculator)->isPositive(5))->toBeTrue();
});

it('adds two numbers', function (): void {
    expect((new MutateCalculator)->add(2, 3))->toBe(5);
});
