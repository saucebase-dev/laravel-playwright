<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Http\Controllers;

use Saucebase\LaravelPlaywright\Services\Truncate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class TruncateController
{

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'connections' => 'nullable|array',
            'connections.*' => 'nullable|string',
        ]);

        /** @var array<string|null> $connections */
        $connections = $request->input('connections') ?? [null];

        $truncate = new Truncate();
        $truncate->truncate($connections);

        return Response::json();
    }

}
