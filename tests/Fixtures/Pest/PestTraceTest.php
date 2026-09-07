<?php

declare(strict_types=1);

it('fails inside a nested closure', function (): void {
    collect([1])->each(function (int $value): void {
        expect($value)->toBe(2);
    });
});
