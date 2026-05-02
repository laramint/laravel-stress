<?php

declare(strict_types=1);

namespace LaraMint\LaravelStress\Tests\Feature;

use Illuminate\Foundation\Application;
use LaraMint\LaravelStress\LaravelStressServiceProvider;
use LaraMint\LaravelStress\StressTestRunner;
use LaraMint\LaravelStress\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_registers_stress_test_runner_in_the_container(): void
    {
        $this->assertInstanceOf(StressTestRunner::class, $this->app->make(StressTestRunner::class));
    }

    public function test_registers_stress_test_runner_as_a_singleton(): void
    {
        $first = $this->app->make(StressTestRunner::class);
        $second = $this->app->make(StressTestRunner::class);

        $this->assertSame($first, $second);
    }

    public function test_is_listed_in_the_registered_providers(): void
    {
        $loaded = array_keys($this->app->getLoadedProviders());

        $this->assertContains(LaravelStressServiceProvider::class, $loaded);
    }

    public function test_does_bind_stress_test_runner_when_env_is_production(): void
    {
        $app = new Application;
        $app['env'] = 'production';

        $provider = new LaravelStressServiceProvider($app);
        $provider->register();

        $this->assertTrue($app->bound(StressTestRunner::class));
    }

    public function test_does_bind_stress_test_runner_when_env_is_local(): void
    {
        $app = new Application;
        $app['env'] = 'local';

        $provider = new LaravelStressServiceProvider($app);
        $provider->register();

        $this->assertTrue($app->bound(StressTestRunner::class));
    }

    public function test_does_bind_stress_test_runner_when_env_is_testing(): void
    {
        $app = new Application;
        $app['env'] = 'testing';

        $provider = new LaravelStressServiceProvider($app);
        $provider->register();

        $this->assertTrue($app->bound(StressTestRunner::class));
    }
}
