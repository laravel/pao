<?php

declare(strict_types=1);

namespace Laravel\Pao\Drivers\Paratest;

use Laravel\Pao\Drivers\Concerns\ProfileCollector;
use Laravel\Pao\Execution;
use ParaTest\Options;
use ParaTest\RunnerInterface;
use ParaTest\WrapperRunner\ResultPrinter;
use ParaTest\WrapperRunner\SuiteLoader;
use ParaTest\WrapperRunner\WrapperRunner as ParatestWrapperRunner;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Configuration\CodeCoverageFilterRegistry;
use PHPUnit\Util\ExcludeList;
use ReflectionObject;
use SplFileInfo;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class WrapperRunner implements RunnerInterface
{
    private ParatestWrapperRunner $runner;

    public function __construct(
        Options $options,
    ) {
        $this->runner = new ParatestWrapperRunner($options, new NullOutput);
    }

    public function run(): int
    {
        $runner = $this->runner;
        $r = new ReflectionObject($runner);

        /** @var non-empty-string $directory */
        $directory = dirname((string) $r->getFileName(), 2);
        ExcludeList::addDirectory($directory);

        /** @var Options $options */
        $options = $r->getProperty('options')->getValue($runner);

        /** @var OutputInterface $output */
        $output = $r->getProperty('output')->getValue($runner);

        /** @var CodeCoverageFilterRegistry $filterRegistry */
        $filterRegistry = $r->getProperty('codeCoverageFilterRegistry')->getValue($runner);

        $suiteLoader = new SuiteLoader($options, $output, $filterRegistry);

        $result = TestResultFacade::result();

        $r->getProperty('pending')->setValue($runner, $suiteLoader->tests);

        /** @var ResultPrinter $printer */
        $printer = $r->getProperty('printer')->getValue($runner);
        $printer->setTestCount($suiteLoader->testCount);
        $printer->start();

        $startTime = hrtime(true);

        $r->getMethod('startWorkers')->invoke($runner);
        $r->getMethod('assignAllPendingTests')->invoke($runner);
        $r->getMethod('waitForAllToFinish')->invoke($runner);

        ProfileCollector::startTimerFromNanoseconds($startTime);
        ProfileCollector::executionStarted();

        /** @var list<SplFileInfo> $testResultFiles */
        $testResultFiles = $r->getProperty('testResultFiles')->getValue($runner);

        $mergedResult = $this->mergeTestResults($result, $testResultFiles);

        if (Execution::running()) {
            $driver = Execution::current()->driver;

            if ($driver instanceof Starter) {
                $driver->testResult = $mergedResult;
            }
        }

        /** @var int $exitCode */
        $exitCode = $r->getMethod('complete')->invoke($runner, $result);

        return $exitCode;
    }

    /**
     * @param  list<SplFileInfo>  $testResultFiles
     */
    private function mergeTestResults(TestResult $sum, array $testResultFiles): TestResult
    {
        foreach ($testResultFiles as $testResultFile) {
            if (! $testResultFile->isFile()) {
                continue;
            }

            $contents = file_get_contents($testResultFile->getPathname());

            if ($contents === false) {
                continue;
            }

            $testResult = unserialize($contents);

            if (! $testResult instanceof TestResult) {
                continue;
            }

            $arguments = [
                (int) $sum->hasTests() + (int) $testResult->hasTests(),
                $sum->numberOfTestsRun() + $testResult->numberOfTestsRun(),
                $sum->numberOfAssertions() + $testResult->numberOfAssertions(),
                [...$sum->testErroredEvents(), ...$testResult->testErroredEvents()],
                [...$sum->testFailedEvents(), ...$testResult->testFailedEvents()],
                array_merge_recursive($sum->testConsideredRiskyEvents(), $testResult->testConsideredRiskyEvents()),
                [...$sum->testSuiteSkippedEvents(), ...$testResult->testSuiteSkippedEvents()],
                [...$sum->testSkippedEvents(), ...$testResult->testSkippedEvents()],
                [...$sum->testMarkedIncompleteEvents(), ...$testResult->testMarkedIncompleteEvents()],
                array_merge_recursive($sum->testTriggeredPhpunitDeprecationEvents(), $testResult->testTriggeredPhpunitDeprecationEvents()),
                array_merge_recursive($sum->testTriggeredPhpunitErrorEvents(), $testResult->testTriggeredPhpunitErrorEvents()),
                array_merge_recursive($sum->testTriggeredPhpunitNoticeEvents(), $testResult->testTriggeredPhpunitNoticeEvents()),
                array_merge_recursive($sum->testTriggeredPhpunitWarningEvents(), $testResult->testTriggeredPhpunitWarningEvents()),
                [...$sum->testRunnerTriggeredDeprecationEvents(), ...$testResult->testRunnerTriggeredDeprecationEvents()],
                [...$sum->testRunnerTriggeredNoticeEvents(), ...$testResult->testRunnerTriggeredNoticeEvents()],
                [...$sum->testRunnerTriggeredWarningEvents(), ...$testResult->testRunnerTriggeredWarningEvents()],
            ];

            $arguments = [
                ...$arguments,
                ...$this->mergeEventLists($sum, $testResult, [
                    'testRunnerTriggeredIssueDeprecationEvents',
                    'testRunnerTriggeredIssueErrorEvents',
                    'testRunnerTriggeredIssueNoticeEvents',
                    'testRunnerTriggeredIssuePhpDeprecationEvents',
                    'testRunnerTriggeredIssuePhpNoticeEvents',
                    'testRunnerTriggeredIssuePhpWarningEvents',
                    'testRunnerTriggeredIssueWarningEvents',
                ]),
                [...$sum->errors(), ...$testResult->errors()],
                [...$sum->deprecations(), ...$testResult->deprecations()],
                [...$sum->notices(), ...$testResult->notices()],
                [...$sum->warnings(), ...$testResult->warnings()],
                [...$sum->phpDeprecations(), ...$testResult->phpDeprecations()],
                [...$sum->phpNotices(), ...$testResult->phpNotices()],
                [...$sum->phpWarnings(), ...$testResult->phpWarnings()],
                $sum->numberOfIssuesIgnoredByBaseline() + $testResult->numberOfIssuesIgnoredByBaseline(),
            ];

            /** @phpstan-ignore-next-line argument.type */
            $sum = new TestResult(...$arguments);
        }

        return $sum;
    }

    /**
     * @param  list<non-empty-string>  $methods
     * @return list<array<mixed>>
     */
    private function mergeEventLists(object $sum, object $testResult, array $methods): array
    {
        $merged = [];

        foreach ($methods as $method) {
            if (! method_exists($sum, $method)) {
                return $merged;
            }

            $first = $sum->{$method}();
            $second = $testResult->{$method}();

            $merged[] = array_merge(
                is_array($first) ? $first : [],
                is_array($second) ? $second : [],
            );
        }

        return $merged;
    }
}
