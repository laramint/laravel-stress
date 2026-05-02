<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use LaraMint\LaravelStress\LaravelStressServiceProvider;
use LaraMint\LaravelStress\StressTestRunner;

describe('LaravelStressServiceProvider – non-production', function () {
    it('registers StressTestRunner in the container', function () {
        expect($this->app->make(StressTestRunner::class))
            ->toBeInstanceOf(StressTestRunner::class);
    });

    it('registers StressTestRunner as a singleton', function () {
        $first = $this->app->make(StressTestRunner::class);
        $second = $this->app->make(StressTestRunner::class);

        expect($first)->toBe($second);
    });

    it('is listed in the registered providers', function () {
        $loaded = array_keys($this->app->getLoadedProviders());

        expect($loaded)->toContain(LaravelStressServiceProvider::class);
    });
});

describe('LaravelStressServiceProvider – production guard', function () {
    it('does not bind StressTestRunner when APP_ENV is production', function () {
        $app = new Application;
        $app['env'] = 'production';

        $provider = new LaravelStressServiceProvider($app);
        $provider->register();

        expect($app->bound(StressTestRunner::class))->toBeFalse();
    });

    it('does bind StressTestRunner when APP_ENV is local', function () {
        $app = new Application;
        $app['env'] = 'local';

        $provider = new LaravelStressServiceProvider($app);
        $provider->register();

        expect($app->bound(StressTestRunner::class))->toBeTrue();
    });

    it('does bind StressTestRunner when APP_ENV is testing', function () {
        $app = new Application;
        $app['env'] = 'testing';

        $provider = new LaravelStressServiceProvider($app);
        $provider->register();

        expect($app->bound(StressTestRunner::class))->toBeTrue();
    });
});
