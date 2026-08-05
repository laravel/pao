<?php

declare(strict_types=1);

namespace Tests\Laravel;

use Laravel\AgentDetector\AgentDetector;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        foreach (array_keys(AgentDetector::AGENT_ENV_VARS) as $envVar) {
            unset($_SERVER[$envVar]);
            putenv($envVar);
        }

        unset($_SERVER['AI_AGENT'], $_SERVER['PAO_DISABLE'], $_SERVER['PAO_FORCE'], $_SERVER['PAO_GUARD_DISABLE']);
        putenv('AI_AGENT');
        putenv('PAO_DISABLE');
        putenv('PAO_FORCE');
        putenv('PAO_GUARD_DISABLE');

        parent::setUp();
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function ignorePackageDiscoveriesFrom(): array
    {
        return ['laravel/pao'];
    }
}
