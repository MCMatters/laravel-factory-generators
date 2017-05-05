<?php

declare(strict_types = 1);

namespace McMatters\FactoryGenerators;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Class ServiceProvider
 *
 * @package McMatters\FactoryGenerators
 */
class ServiceProvider extends BaseServiceProvider
{
    /**
     * Boot provider.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/factory-generators.php' => config_path(
                'factory-generators.php'
            ),
        ]);
    }
}
