<?php

namespace Binaryk\LaravelRestify\Tests\Unit;

use Binaryk\LaravelRestify\Repositories\Repository;
use Binaryk\LaravelRestify\Restify;
use Binaryk\LaravelRestify\Tests\IntegrationTest;
use Illuminate\Routing\Router;

class RepositoryWithRoutesTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadRepositories();

        Restify::repositories([
            RepositoryWithRoutes::class,
            WithCustomPrefix::class,
            WithCustomMiddleware::class,
            WithCustomNamespace::class,
            WithoutGroup::class,
        ]);
    }

    public function test_can_add_custom_routes(): void
    {
        $this->getJson(RepositoryWithRoutes::to('main-testing'))
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);


        $this->getJson(route('main.testing.route'))
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_use_custom_prefix(): void
    {
        $this->getJson('/custom-prefix/testing')->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_use_custom_middleware(): void
    {
        $this->getJson(route('middleware.failing.route'))->assertStatus(403);
    }

    public function test_can_use_custom_namespace(): void
    {
        $this->getJson(route('namespace.route'))
            ->assertOk()
            ->assertJson([
                'meta' => [
                    'message' => 'From the sayHello method',
                ],
            ]);
    }

    public function test_routes_default_wrapped()
    {
        $this->withoutExceptionHandling()->getJson(route('no.group.default.options'))
            ->assertOk()
            ->assertJson([
                'meta' => [
                    'message' => 'From the sayHello method',
                ],
            ]);
    }
}

class RepositoryWithRoutes extends Repository
{
    public static function routes(Router $router, array $attributes, $wrap = false)
    {
        $router->group($attributes, function ($router) {
            $router->get('/main-testing', function () {
                return response()->json([
                    'success' => true,
                ]);
            })->name('main.testing.route');
        });
    }

    public static function uriKey(): string
    {
        return 'posts';
    }
}

class WithCustomPrefix extends RepositoryWithRoutes
{
    public static function routes(Router $router, array $attributes, $wrap = false)
    {
        $attributes['prefix'] = 'custom-prefix';

        $router->group($attributes, function ($router) {
            $router->get('testing', function () {
                return response()->json([
                    'success' => true,
                ]);
            })->name('custom.testing.route');
        });
    }
}

class MiddlewareFail
{
    public function handle($request, $next)
    {
        if (true) {
            return abort(403);
        }
    }
}

class WithCustomMiddleware extends RepositoryWithRoutes
{
    public static function routes(Router $router, array $attributes, $wrap = false)
    {
        $attributes['middleware'] = [MiddlewareFail::class];

        $router->group($attributes, function ($router) {
            $router->get('with-middleware', function () {
                return response()->json([
                    'success' => true,
                ]);
            })->name('middleware.failing.route');
        });
    }
}

class WithCustomNamespace extends RepositoryWithRoutes
{
    public static function routes(Router $router, array $attributes, $wrap = false)
    {
        $attributes['namespace'] = 'Binaryk\LaravelRestify\Tests\Unit';

        $router->group($attributes, function ($router) {
            $router->get('custom-namespace', function () {
                return response()->json([
                    'meta' => [
                        'message' => 'From the sayHello method',
                    ],
                ]);
            })->name('namespace.route');
        });
    }
}

class WithoutGroup extends RepositoryWithRoutes
{
    public static function routes(Router $router, array $attributes, $wrap = true)
    {
        $router->get('default-options', function () {
            return response()->json([
                'meta' => [
                    'message' => 'From the sayHello method',
                ],
            ]);
        })->name('no.group.default.options');
    }
}
