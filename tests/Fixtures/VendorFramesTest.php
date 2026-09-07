<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Container\Container;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class VendorFramesTest extends TestCase
{
    public function test_it_fails_through_vendor_frames(): void
    {
        (new Pipeline(new Container))
            ->send('payload')
            ->through([
                fn (string $payload, \Closure $next): mixed => $next($payload),
                fn (string $payload, \Closure $next): mixed => $next($payload),
                fn (string $payload, \Closure $next): mixed => $next($payload),
            ])
            ->then(function (string $payload): void {
                $this->assertSame('expected', $payload);
            });
    }

    public function test_it_errors_inside_a_vendor_frame(): void
    {
        (new Collection)->firstOrFail();
    }
}
