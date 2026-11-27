<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProviderArgumentsTest extends TestCase
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function tooManyArgumentsProvider(): array
    {
        return [
            'arguments' => getenv('PAO_TRIGGER_PHPUNIT_WARNING') === '1' ? ['a', 'b'] : ['a'],
        ];
    }

    #[DataProvider('tooManyArgumentsProvider')]
    public function test_provider_gives_more_arguments_than_the_test_accepts(string $a): void
    {
        $this->assertSame('a', $a);
    }
}
