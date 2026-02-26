<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Http\Controllers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class FactoryController
{

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'model' => 'string|required',
            'count' => 'nullable|integer',
            'attrs' => 'array',
        ]);

        $modelClass = (string) $request->string('model');
        $count = $request->has('count') ? $request->integer('count') : null;
        /** @var array<string, mixed> $attrs */
        $attrs = (array) $request->input('attrs');

        if (!class_exists($modelClass)) {
            $modelClass = 'App\\Models\\' . $modelClass;
        }

        if (!class_exists($modelClass)) {
            abort(422, 'Model not found');
        }

        $model = app($modelClass);

        if (!$model instanceof Model) {
            abort(422, 'Model not found');
        }

        if (!method_exists($model, 'factory')) {
            abort(422, 'Model factory not found');
        }

        /** @var Factory<Model> $modelFactory */
        $modelFactory = $model->factory();

        if ($count !== null) {
            $modelFactory = $modelFactory->count($count);
        }

        $models = $modelFactory->create($attrs);

        return Response::json($models);
    }

}
