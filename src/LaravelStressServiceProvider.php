<?php

declare(strict_types=1);

namespace LaraMint\LaravelStress;

use Illuminate\Support\ServiceProvider;

class LaravelStressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StressTestRunner::class);
    }
}
