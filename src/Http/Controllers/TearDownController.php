<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Http\Controllers;

use Saucebase\LaravelPlaywright\Services\DynamicConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class TearDownController
{

    public function __invoke(Request $request): JsonResponse
    {
        DynamicConfig::delete(DynamicConfig::id($request));

        return Response::json();
    }

}
