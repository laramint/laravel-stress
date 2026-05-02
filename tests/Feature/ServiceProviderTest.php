<?php

declare(strict_types=1);

use LaraMint\LaravelStress\LaravelStressServiceProvider;
use LaraMint\LaravelStress\StressTestRunner;

describe('LaravelStressServiceProvider', function () {
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
