<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Http\Controllers;

use Carbon\Carbon;
use Saucebase\LaravelPlaywright\Services\DynamicConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class TravelController
{

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'to' => 'string|required',
        ]);

        $to = (string) $request->string('to');

        try {
            Carbon::parse($to);
        } catch (\Exception $e) {
            abort(422, 'Invalid date');
        }

        DynamicConfig::set(DynamicConfig::KEY_TRAVEL, $to);

        return Response::json();
    }

}
