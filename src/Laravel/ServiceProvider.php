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
        if (isset($_SERVER['PAO_DISABLE'])) {
            return;
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        if ($this->app->runningUnitTests()) {
            return;
        }

        if (! AgentDetector::detect()->isAgent && ! ($_SERVER['PAO_FORCE'] ?? false)) {
            return;
        }

        $this->app->bind(OutputStyle::class, PaoOutputStyle::class);

        /** @var Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);
        $events->listen(CommandStarting::class, function (CommandStarting $event): void {
            $event->output->setDecorated(false);
        });
    }
}
