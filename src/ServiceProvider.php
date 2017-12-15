<?php

declare(strict_types = 1);

namespace McMatters\FactoryGenerators;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use McMatters\FactoryGenerators\Console\Commands\Generate;
use const DIRECTORY_SEPARATOR;

/**
 * Class ServiceProvider
 *
 * @package McMatters\FactoryGenerators
 */
class ServiceProvider extends BaseServiceProvider
{
    /**
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__.'/../config/factory-generators.php';

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $configPath => $this->app->configPath().DIRECTORY_SEPARATOR.'factory-generators.php',
            ], 'config');
        }

        $this->mergeConfigFrom($configPath, 'factory-generators');
    }

    /**
     * @return void
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function register()
    {
        $this->app->singleton('command.factory-generators.generate', function ($app) {
            return new Generate($app);
        });

        $this->commands([
            'command.factory-generators.generate',
        ]);
    }
}
