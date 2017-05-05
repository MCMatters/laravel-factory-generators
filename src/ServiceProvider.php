<?php

declare(strict_types = 1);

namespace McMatters\FactoryGenerators;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use McMatters\FactoryGenerators\Console\Commands\Generate;

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

    /**
     * Register methods.
     */
    public function register()
    {
        $this->app->singleton('command.factory-generators.generate', function () {
            return new Generate();
        });

        $this->commands([
            'command.factory-generators.generate',
        ]);
    }
}
