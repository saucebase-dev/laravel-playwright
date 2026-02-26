<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class QueryController
{

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'connection' => 'nullable|string',
            'query' => 'string|required',
            'bindings' => 'array',
            'unprepared' => 'boolean',
        ]);

        $connection = $request->has('connection') ?
            (string) $request->string('connection') :
            null;
        $query = (string) $request->string('query');
        /** @var array<mixed> $bindings */
        $bindings = $request->input('bindings', []);
        $unprepared = $request->boolean('unprepared');

        $connection = DB::connection($connection);
        $success = $unprepared ?
            $connection->unprepared($query) :
            $connection->statement($query, $bindings);

        return Response::json([
            'success' => $success,
        ]);
    }

}
