<?php

declare(strict_types=1);

namespace Laravel\Pao\Drivers\PestMutate;

use Laravel\Pao\Drivers\Concerns\ProfileCollector;
use Laravel\Pao\Drivers\Starter as BaseStarter;
use Pest\Mutate\Contracts\Mutator;
use Pest\Mutate\MutationSuite;
use Pest\Mutate\Repositories\MutationRepository;
use Pest\Mutate\Support\MutationTestResult;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Throwable;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @phpstan-type UntestedDetail array{file: string, line: int, mutator: string, diff?: array{original: string, mutated: string}}
 */
final class Starter extends BaseStarter
{
    public function name(): string
    {
        return 'pest-mutate';
    }

    public function start(): void
    {
        ProfileCollector::startTimerFromNanoseconds(hrtime(true));

        $this->discardStdout();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parse(): ?array
    {
        if (! class_exists(MutationSuite::class, false)) {
            return null;
        }

        try {
            $repository = MutationSuite::instance()->repository;

            return $this->omitEmpty([
                'score' => round($repository->score(), 2),
                'mutations' => $repository->total(),
                'tested' => $repository->tested(),
                'untested' => $repository->untested(),
                'uncovered' => $repository->uncovered(),
                'timeout' => $repository->timedOut(),
                'pending' => $repository->notRun(),
                'duration_ms' => ProfileCollector::durationMs(),
                'untested_details' => $this->untestedDetails($repository),
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function omitEmpty(array $result): array
    {
        foreach (['tested', 'untested', 'uncovered', 'timeout', 'pending'] as $key) {
            if ($result[$key] === 0) {
                unset($result[$key]);
            }
        }

        if ($result['untested_details'] === []) {
            unset($result['untested_details']);
        }

        return $result;
    }

    /**
     * @return list<UntestedDetail>
     */
    private function untestedDetails(MutationRepository $repository): array
    {
        $cwd = getcwd();
        $prefix = $cwd === false ? '' : $cwd.DIRECTORY_SEPARATOR;

        $details = [];

        foreach ($repository->all() as $collection) {
            foreach ($collection->tests() as $test) {
                if ($test->result() !== MutationTestResult::Untested) {
                    continue;
                }

                $mutation = $test->mutation;

                $file = $mutation->file->getRealPath();
                $file = $file === false ? $mutation->file->getPathname() : $file;

                if ($prefix !== '' && str_starts_with($file, $prefix)) {
                    $file = substr($file, strlen($prefix));
                }

                /** @var class-string<Mutator> $mutator */
                $mutator = $mutation->mutator;

                $detail = [
                    'file' => $file,
                    'line' => $mutation->startLine,
                    'mutator' => $mutator::name(),
                ];

                $diff = $this->formatDiff($mutation->diff);

                if ($diff !== null) {
                    $detail['diff'] = $diff;
                }

                $details[] = $detail;
            }
        }

        return $details;
    }

    /**
     * @return array{original: string, mutated: string}|null
     */
    private function formatDiff(string $diff): ?array
    {
        if ($diff === '') {
            return null;
        }

        $plain = (new OutputFormatter)->format($diff) ?? '';

        $original = [];
        $mutated = [];

        foreach (explode("\n", $plain) as $line) {
            $line = trim($line);

            if (str_starts_with($line, '-') && ! str_starts_with($line, '---')) {
                $original[] = trim(substr($line, 1));
            } elseif (str_starts_with($line, '+') && ! str_starts_with($line, '+++')) {
                $mutated[] = trim(substr($line, 1));
            }
        }

        if ($original === [] && $mutated === []) {
            return null;
        }

        return [
            'original' => implode("\n", $original),
            'mutated' => implode("\n", $mutated),
        ];
    }
}
