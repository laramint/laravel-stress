<?php

declare(strict_types=1);

namespace LaraMint\LaravelStress\Tests;

use LaraMint\LaravelStress\LaravelStressServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelStressServiceProvider::class];
    }
}
