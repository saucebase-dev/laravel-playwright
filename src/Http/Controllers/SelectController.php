<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class SelectController
{

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'connection' => 'nullable|string',
            'query' => 'string|required',
            'bindings' => 'array',
        ]);

        $connection = $request->has('connection') ?
            (string) $request->string('connection') :
            null;
        $query = (string) $request->string('query');
        /** @var array<mixed> $bindings */
        $bindings = $request->input('bindings', []);

        $results = DB::connection($connection)->select($query, $bindings);

        return Response::json($results);
    }

}
