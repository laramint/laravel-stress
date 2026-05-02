<?php

declare(strict_types=1);

namespace LaraMint\LaravelStress;

use Illuminate\Support\ServiceProvider;

class LaravelStressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->isProduction()) {
            return;
        }

        $this->app->singleton(StressTestRunner::class);
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            return;
        }
    }
}
