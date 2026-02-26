<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Http\Controllers;

use Saucebase\LaravelPlaywright\Services\DynamicConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class RegisterBootFunctionController
{

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'function' => 'string|required',
        ]);

        $function = (string) $request->string('function');

        if (!is_callable($function))
            abort(422, 'Function is not callable');

        $currentBootFunctions = DynamicConfig::get(DynamicConfig::KEY_BOOT_FUNCTIONS, []);
        assert(is_array($currentBootFunctions));
        $currentBootFunctions[] = $function;

        DynamicConfig::set(DynamicConfig::KEY_BOOT_FUNCTIONS, $currentBootFunctions);

        return Response::json();
    }

}
