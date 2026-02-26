<?php

use Saucebase\LaravelPlaywright\Http\Controllers\ArtisanController;
use Saucebase\LaravelPlaywright\Http\Controllers\DynamicConfigController;
use Saucebase\LaravelPlaywright\Http\Controllers\FactoryController;
use Saucebase\LaravelPlaywright\Http\Controllers\FunctionController;
use Saucebase\LaravelPlaywright\Http\Controllers\QueryController;
use Saucebase\LaravelPlaywright\Http\Controllers\RegisterBootFunctionController;
use Saucebase\LaravelPlaywright\Http\Controllers\SelectController;
use Saucebase\LaravelPlaywright\Http\Controllers\TearDownController;
use Saucebase\LaravelPlaywright\Http\Controllers\TravelController;
use Saucebase\LaravelPlaywright\Http\Controllers\TruncateController;
use Saucebase\LaravelPlaywright\Http\Middleware\VerifyPlaywrightSecret;
use Saucebase\LaravelPlaywright\Services\Config as LaravelPlaywrightConfig;
use Illuminate\Support\Facades\Route;


Route::prefix(LaravelPlaywrightConfig::prefix())
    ->middleware(VerifyPlaywrightSecret::class)
    ->group(
        function () {
            Route::post('artisan', ArtisanController::class);
            Route::post('dynamicConfig', DynamicConfigController::class);
            Route::post('factory', FactoryController::class);
            Route::post('function', FunctionController::class);
            Route::post('query', QueryController::class);
            Route::post('registerBootFunction', RegisterBootFunctionController::class);
            Route::post('select', SelectController::class);
            Route::post('tearDown', TearDownController::class);
            Route::post('travel', TravelController::class);
            Route::post('truncate', TruncateController::class);
        }
    );
