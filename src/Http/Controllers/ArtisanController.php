<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Response;

class ArtisanController
{

    public function __invoke(Request $request): JsonResponse
    {
        $command = (string) $request->string('command');
        $parameters = (array) $request->input('parameters');

        $exitCode = Artisan::call($command, $parameters);

        return Response::json([
            'code' => $exitCode,
            'output' => Artisan::output(),
        ]);
    }

}
