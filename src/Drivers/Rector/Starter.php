<?php

declare(strict_types=1);

namespace Laravel\Pao\Drivers\Rector;

use Laravel\Pao\Drivers\Starter as BaseStarter;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class Starter extends BaseStarter
{
    private ?int $outputBufferLevel = null;

    public function name(): string
    {
        return 'rector';
    }

    public function start(): void
    {
        /** @var array<int, string> $argv */
        $argv = $_SERVER['argv'];

        if (! $this->shouldTransform($argv)) {
            return;
        }

        $argv = $this->ensureOutputFormatJson($argv);
        $_SERVER['argv'] = $argv;
        $GLOBALS['argv'] = $argv;

        $this->outputBufferLevel = ob_get_level();
        ob_start();
    }

    /**
     * @param  array<int, string>  $argv
     */
    private function shouldTransform(array $argv): bool
    {
        $command = $this->commandName($argv);

        if ($command === null || in_array($command, ['process', 'p'], true)) {
            return true;
        }

        return file_exists($command) || str_starts_with($argv[1] ?? '', '-');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parse(): ?array
    {
        $captured = trim($this->bufferedOutput());

        if ($captured === '') {
            return null;
        }

        $start = strpos($captured, '{');

        if ($start !== false && $start > 0) {
            $captured = substr($captured, $start);
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($captured, associative: true);

        if (! is_array($data) || ! is_array($data['totals'] ?? null)) {
            $fatalErrors = is_array($data) ? $this->fatalErrors($data) : [];

            if ($fatalErrors !== []) {
                return [
                    'result' => 'failed',
                    'fatal_errors' => $fatalErrors,
                ];
            }

            return [
                'raw' => [$captured],
            ];
        }

        $changedFiles = $data['totals']['changed_files'] ?? 0;
        $errors = $data['totals']['errors'] ?? 0;

        if (! is_int($changedFiles) || ! is_int($errors)) {
            return [
                'raw' => [$captured],
            ];
        }

        return [
            'result' => $errors > 0 || ($changedFiles > 0 && $this->isDryRun()) ? 'failed' : 'passed',
        ] + $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function fatalErrors(array $data): array
    {
        if (! is_array($data['fatal_errors'] ?? null)) {
            return [];
        }

        $errors = [];

        foreach ($data['fatal_errors'] as $error) {
            if (is_string($error) && trim($error) !== '') {
                $errors[] = trim($error);
            }
        }

        return $errors;
    }

    private function bufferedOutput(): string
    {
        if ($this->outputBufferLevel === null) {
            return '';
        }

        $buffered = '';

        while (ob_get_level() > $this->outputBufferLevel) {
            $buffer = ob_get_clean();

            if ($buffer === false) {
                break;
            }

            $buffered = $buffer.$buffered;
        }

        return $buffered;
    }

    /**
     * @param  array<int, string>  $argv
     * @return array<int, string>
     */
    private function ensureOutputFormatJson(array $argv): array
    {
        $filtered = [];
        $skipNext = false;

        foreach ($argv as $arg) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if (str_starts_with($arg, '--output-format=')) {
                continue;
            }

            if ($arg === '--output-format') {
                $skipNext = true;

                continue;
            }

            $filtered[] = $arg;
        }

        return $this->addOption($filtered, '--output-format=json');
    }

    private function isDryRun(): bool
    {
        /** @var array<int, string> $argv */
        $argv = $_SERVER['argv'] ?? [];

        foreach ($argv as $arg) {
            if (in_array($arg, ['--dry-run', '-n'], true)) {
                return true;
            }
        }

        return false;
    }
}
