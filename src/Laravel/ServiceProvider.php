<?php

declare(strict_types=1);

namespace Laravel\Pao\Laravel;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Laravel\AgentDetector\AgentDetector;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class ServiceProvider extends LaravelServiceProvider
{
    public function boot(): void
    {
        if (filter_var($_SERVER['PAO_DISABLE'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        if ($this->app->runningUnitTests()) {
            return;
        }

        if ($this->shouldGuardDestructiveCommands()) {
            $this->app->make(DestructiveCommandGuard::class)->activate();
        }

        if (! AgentDetector::detect()->isAgent && ! filter_var($_SERVER['PAO_FORCE'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $this->app->bind(OutputStyle::class, PaoOutputStyle::class);

        /** @var Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);
        $events->listen(CommandStarting::class, function (CommandStarting $event): void {
            $event->output->setDecorated(false);
        });
    }

    private function shouldGuardDestructiveCommands(): bool
    {
        if (! AgentDetector::detect()->isAgent && ! filter_var($_SERVER['PAO_FORCE'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return ! filter_var($_SERVER['PAO_GUARD_DISABLE'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
