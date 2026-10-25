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

        // Commands expect the CLI's working directory, not the web server's public/:
        // `migrate:fresh` on SQLite empties a relative DB_DATABASE path.
        $previous = getcwd();
        chdir(base_path());

        try {
            $exitCode = Artisan::call($command, $parameters);
        } finally {
            if ($previous !== false) {
                chdir($previous);
            }
        }

        return Response::json([
            'code' => $exitCode,
            'output' => Artisan::output(),
        ]);
    }

}
