<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Http\Controllers;

use Saucebase\LaravelPlaywright\Services\DynamicConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class DynamicConfigController
{

    public function __invoke(Request $request): JsonResponse
    {
        DynamicConfig::requireId($request);

        $request->validate([
            'key' => 'string|required',
            'value' => 'required',
        ]);

        $key = (string) $request->string('key');
        $value = $request->input('value');

        DynamicConfig::set(DynamicConfig::requireId($request), $key, $value);

        return Response::json();
    }

}
