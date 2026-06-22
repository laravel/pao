<?php

declare(strict_types=1);

require_once __DIR__.'/src/Noop.php';

covers(MutateNoop::class);

it('runs without mutable statements', function (): void {
    (new MutateNoop)->run();

    expect(true)->toBeTrue();
});
