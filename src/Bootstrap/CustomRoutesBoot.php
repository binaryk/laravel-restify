<?php

namespace Binaryk\LaravelRestify\Bootstrap;

use Binaryk\LaravelRestify\Repositories\Repository;
use Binaryk\LaravelRestify\Restify;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionParameter;

class CustomRoutesBoot
{
    public function __construct(
        private ?array $repositories = null
    ) {
        $this->repositories = $this->repositories ?? Restify::$repositories;
    }

    public function boot(): void
    {
        collect($this->repositories)->each(function ($repository) {
            $config = [
                'namespace' => trim(app()->getNamespace(), '\\').'\Http\Controllers',
                'as' => '',
                'prefix' => Restify::path($repository::uriKey()),
                'middleware' => config('restify.middleware', []),
            ];

            $reflector = new ReflectionClass($repository);

            $method = $reflector->getMethod('routes');

            $parameters = $method->getParameters();

            $wrap = [];

            if (count($parameters) >= 2 && $parameters[1] instanceof ReflectionParameter) {
                $default = $parameters[1]->isDefaultValueAvailable() ? $parameters[1]->getDefaultValue() : [];
                $config = array_merge($config, $default);
            }

            if (count($parameters) === 3) {
                $wrap = ($parameters[2]->isDefaultValueAvailable() && $parameters[2]->getDefaultValue()) ? $config : [];
            }

            /** * @var Repository $repository */
            Route::group($wrap, function ($router) use ($repository, $config) {
                $repository::routes($router, $config);
            });
        });
    }
}
