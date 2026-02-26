<?php

declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Services;

use Illuminate\Support\Facades\Config as LaravelConfig;

class Config
{

    public static function prefix(): string
    {
        /** @var string $prefix */
        $prefix = LaravelConfig::get('laravel-playwright.prefix', 'playwright');

        return $prefix;
    }

    /**
     * @return string[]
     */
    public static function envs(): array
    {
        /** @var string[] $envs */
        $envs = LaravelConfig::get('laravel-playwright.environments', ['local', 'testing']);

        return $envs;
    }

    public static function secret(): ?string
    {
        /** @var string|null $secret */
        $secret = LaravelConfig::get('laravel-playwright.secret', null);
        
        return $secret;
    }
}
