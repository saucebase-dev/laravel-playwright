<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Saucebase\LaravelPlaywright\ServiceProvider;
use Saucebase\LaravelPlaywright\Tests\TestCase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

class RouteEnvTest extends TestCase
{

    public function testDoesNotAddRoutesForProduction(): void
    {
        $this->post('playwright/truncate')->assertOk();

        assert($this->app !== null);

        $this->app->detectEnvironment(fn() => 'production');
        $this->app->register(ServiceProvider::class, true);
        $this->app->offsetSet('env', 'production');

        Route::setRoutes(new RouteCollection());
        (new ServiceProvider($this->app))->boot();
        $this->post('playwright/artisan')->assertNotFound();
    }

    /**
     * The endpoints run arbitrary PHP and SQL, so the developer's own `local`
     * server must never expose them. E2E runs against a dedicated environment.
     */
    public function testRoutesAreOffInLocalAndOnInE2e(): void
    {
        assert($this->app !== null);

        $this->app->offsetSet('env', 'local');
        Route::setRoutes(new RouteCollection());
        (new ServiceProvider($this->app))->boot();
        $this->post('playwright/truncate')->assertNotFound();

        $this->app->offsetSet('env', 'e2e');
        Route::setRoutes(new RouteCollection());
        (new ServiceProvider($this->app))->boot();
        $this->post('playwright/truncate')->assertOk();
    }

    public function testSupportsCustomPrefix(): void
    {
        config(['laravel-playwright.prefix' => 'api/e2e']);

        assert($this->app !== null);

        Route::setRoutes(new RouteCollection());
        (new ServiceProvider($this->app))->boot();

        $this->post('playwright/truncate')->assertNotFound();
        $this->post('api/e2e/truncate')->assertOk();
    }

}