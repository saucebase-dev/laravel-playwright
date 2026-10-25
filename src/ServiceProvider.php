<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright;

use Saucebase\LaravelPlaywright\Console\EnvCommand;
use Saucebase\LaravelPlaywright\Services\Config;
use Saucebase\LaravelPlaywright\Services\DynamicConfig;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{

    public function register() : void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-playwright.php', 'laravel-playwright');
    }

    public function boot() : void
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-playwright.php' => config_path('laravel-playwright.php'),
        ], 'laravel-playwright-config');

        // Writing .env.e2e happens from the developer's own environment, so in any.
        if ($this->app->runningInConsole()) {
            $this->commands([EnvCommand::class]);
        }

        if (App::environment(...Config::envs())) {
            $this->loadRoutesFrom(__DIR__ . '/routes/e2e.php');

            // The HTTP kernel binds the request before providers boot, so a test's
            // state is in place before anything else in the app reads config.
            DynamicConfig::load(DynamicConfig::id($this->app->make('request')));
        }

    }

}