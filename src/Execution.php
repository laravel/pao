<?php

declare(strict_types=1);

namespace Laravel\Pao;

use Laravel\AgentDetector\AgentResult;
use Laravel\Pao\Contracts\Driver;
use Laravel\Pao\Exceptions\ShouldNotHappenException;
use Laravel\Pao\UserFilters\CaptureFilter;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @phpstan-type TestDetail array{test: string, file: string, line: int, message: string}
 * @phpstan-type ProfileEntry array{test: string, file: string, duration_ms: int}
 * @phpstan-type Result array{result: 'passed'|'failed', tests: int, passed: int, duration_ms: int, failed?: int, failures?: list<TestDetail>, errors?: int, error_details?: list<TestDetail>, skipped?: int, profile?: list<ProfileEntry>, raw?: list<string>}
 */
final class Execution
{
    private static ?self $instance = null;

    /**
     * @param  resource|null  $stdout
     * @param  resource|null  $filter
     */
    private function __construct(
        public readonly AgentResult $agent,
        public readonly Driver $driver,
        public mixed $stdout = null,
        public mixed $filter = null,
    ) {
        //
    }

    /**
     * @param  array<int, string>  $argv
     */
    public static function start(AgentResult $agent, array $argv): void
    {
        if (self::running()) {
            throw new ShouldNotHappenException;
        }

        $binary = basename($argv[0] ?? '');

        $starter = match ($binary) {
            'paratest' => new Drivers\Paratest\Starter,
            'pest' => self::resolvePestStarter($argv),
            'phpstan', 'phpstan.phar' => new Drivers\Phpstan\Starter,
            'phpunit' => new Drivers\Phpunit\Starter,
            'rector' => new Drivers\Rector\Starter,
            default => null,
        };

        if ($starter instanceof Driver) {
            self::$instance = new self(
                $agent,
                $starter,
            );

            $starter->start();
        }
    }

    /**
     * @param  array<int, string>  $argv
     */
    private static function resolvePestStarter(array $argv): Driver
    {
        return in_array('--mutate', $argv, true)
            ? new Drivers\PestMutate\Starter
            : new Drivers\Pest\Starter;
    }

    public static function running(): bool
    {
        return self::$instance instanceof Execution;
    }

    public static function current(): self
    {
        return self::$instance ?? throw new ShouldNotHappenException;
    }

    public function restoreStdout(): void
    {
        if (is_resource($this->filter)) {
            stream_filter_remove($this->filter);

            $this->filter = null;
        }
    }

    public function flushStdout(): void
    {
        if (! is_resource($this->filter)) {
            return;
        }

        $captured = CaptureFilter::output();

        $this->restoreStdout();

        if ($captured !== '') {
            fwrite(STDOUT, $captured);
        }
    }
}
