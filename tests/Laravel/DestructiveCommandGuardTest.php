<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Support\Facades\DB;
use Laravel\Pao\Laravel\DestructiveCommandGuard;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Laravel\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    DB::prohibitDestructiveCommands(false);
});

it('prohibits all five destructive commands', function (): void {
    app()->make(DestructiveCommandGuard::class)->activate();

    foreach ([
        FreshCommand::class,
        RefreshCommand::class,
        ResetCommand::class,
        RollbackCommand::class,
        WipeCommand::class,
    ] as $command) {
        expect(prohibitedFlag($command))->toBeTrue();
    }
});

it('writes the guidance message when a guarded command starts', function (): void {
    $output = new BufferedOutput;

    app()->make(DestructiveCommandGuard::class)->activate();

    /** @var Dispatcher $dispatcher */
    $dispatcher = app(Dispatcher::class);
    $dispatcher->dispatch(new CommandStarting('migrate:fresh', new ArrayInput([]), $output));

    expect($output->fetch())->toContain('Ask the user to run it manually');
});

it('does not write the message for unrelated commands', function (): void {
    $output = new BufferedOutput;

    app()->make(DestructiveCommandGuard::class)->activate();

    /** @var Dispatcher $dispatcher */
    $dispatcher = app(Dispatcher::class);
    $dispatcher->dispatch(new CommandStarting('migrate:status', new ArrayInput([]), $output));

    expect($output->fetch())->toBe('');
});

it('exposes the full list of guarded command names', function (): void {
    expect(DestructiveCommandGuard::GUARDED_COMMANDS)->toBe([
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
    ]);
});

it('returns the guidance message', function (): void {
    expect(app()->make(DestructiveCommandGuard::class)->message())
        ->toContain('dangerous command')
        ->toContain('Ask the user to run it manually');
});

function prohibitedFlag(string $commandClass): bool
{
    $property = (new ReflectionClass($commandClass))->getProperty('prohibitedFromRunning');
    $property->setAccessible(true);

    return (bool) $property->getValue();
}
