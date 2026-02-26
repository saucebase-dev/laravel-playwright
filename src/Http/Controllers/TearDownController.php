<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Http\Controllers;

use Saucebase\LaravelPlaywright\Services\DynamicConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class TearDownController
{

    public function __invoke(DynamicConfig $dynamicConfig): JsonResponse
    {
        $dynamicConfig->delete();

        return Response::json();
    }

}
