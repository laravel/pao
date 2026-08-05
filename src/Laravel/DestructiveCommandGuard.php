<?php

declare(strict_types=1);

namespace Laravel\Pao\Laravel;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * @internal
 */
final readonly class DestructiveCommandGuard
{
    /**
     * @var list<string>
     */
    public const array GUARDED_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
    ];

    public function __construct(private Dispatcher $events) {}

    public function activate(): void
    {
        DB::prohibitDestructiveCommands(true);

        $this->events->listen(CommandStarting::class, function (CommandStarting $event): void {
            if (in_array($event->command, self::GUARDED_COMMANDS, true)) {
                $event->output->writeln('<fg=yellow>'.$this->message().'</>');
            }
        });
    }

    public function message(): string
    {
        return 'That is a dangerous command and has not been executed. Ask the user to run it manually, then continue.';
    }
}
