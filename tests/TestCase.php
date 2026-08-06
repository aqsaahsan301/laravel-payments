<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments\Tests;

use Aqsaahsan301\LaravelPayments\LaravelPaymentsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelPaymentsServiceProvider::class,
        ];
    }
}
