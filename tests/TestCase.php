<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests;

use Fillindev\Support\SupportServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SupportServiceProvider::class,
        ];
    }
}
