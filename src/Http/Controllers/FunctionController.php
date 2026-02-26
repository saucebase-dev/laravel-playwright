<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class FunctionController
{

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'function' => 'string|required',
            'args' => 'array',
        ]);

        $function = (string) $request->string('function');
        /** @var array<mixed> $args */
        $args = $request->input('args', []);

        if (!is_callable($function))
            abort(422, 'Function does not exist');

        $response = call_user_func_array($function, $args);

        return Response::json($response);
    }

}
