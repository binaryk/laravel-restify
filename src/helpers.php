<?php

use Binaryk\LaravelRestify\Fields\Field;
use Binaryk\LaravelRestify\Restify;
use Illuminate\Http\JsonResponse;

if (! function_exists('field')) {
    function field(...$args): Field
    {
        return Field::new(...$args);
    }
}

if (! function_exists('isRestify')) {
    function isRestify(\Illuminate\Http\Request $request): bool
    {
        return Restify::isRestify($request);
    }
}

if (! function_exists('data')) {
    function data(mixed $data = [], int $status = 200, array $headers = [], $options = 0): JsonResponse
    {
        return response()->json([
            'data' => $data,
        ], $status, $headers, $options);
    }
}
