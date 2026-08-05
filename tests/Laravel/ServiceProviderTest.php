<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Laravel\Pao\Laravel\PaoOutputStyle;
use Laravel\Pao\Laravel\ServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\Laravel\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    unset($_SERVER['AI_AGENT'], $_SERVER['PAO_DISABLE'], $_SERVER['PAO_FORCE'], $_SERVER['PAO_GUARD_DISABLE']);
    putenv('AI_AGENT');
    putenv('PAO_GUARD_DISABLE');
});

it('does not bind PaoOutputStyle when not in agent mode', function (): void {
    $this->app->register(ServiceProvider::class, true);

    expect($this->app->make(OutputStyle::class, [
        'input' => new ArrayInput([]),
        'output' => new NullOutput,
    ]))->not->toBeInstanceOf(PaoOutputStyle::class);
});

it('does not bind PaoOutputStyle when PAO_DISABLE is set', function (): void {
    $_SERVER['AI_AGENT'] = '1';
    $_SERVER['PAO_DISABLE'] = '1';

    $this->app->register(ServiceProvider::class, true);

    expect($this->app->make(OutputStyle::class, [
        'input' => new ArrayInput([]),
        'output' => new NullOutput,
    ]))->not->toBeInstanceOf(PaoOutputStyle::class);
});

it('does not guard destructive commands when no agent is detected', function (): void {
    expect(shouldGuardDestructiveCommands(new ServiceProvider($this->app)))->toBeFalse();
});

it('guards destructive commands when an agent is detected', function (): void {
    putenv('AI_AGENT=1');

    expect(shouldGuardDestructiveCommands(new ServiceProvider($this->app)))->toBeTrue();
});

it('does not guard destructive commands when PAO_GUARD_DISABLE is set', function (): void {
    putenv('AI_AGENT=1');
    $_SERVER['PAO_GUARD_DISABLE'] = '1';

    expect(shouldGuardDestructiveCommands(new ServiceProvider($this->app)))->toBeFalse();
});

function shouldGuardDestructiveCommands(ServiceProvider $provider): bool
{
    $method = (new ReflectionClass($provider))->getMethod('shouldGuardDestructiveCommands');
    $method->setAccessible(true);

    return (bool) $method->invoke($provider);
}
