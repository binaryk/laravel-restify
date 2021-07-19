<?php

namespace Binaryk\LaravelRestify;

use Binaryk\LaravelRestify\Http\Controllers\Auth\ForgotPasswordController;
use Binaryk\LaravelRestify\Http\Controllers\Auth\LoginController;
use Binaryk\LaravelRestify\Http\Controllers\Auth\RegisterController;
use Binaryk\LaravelRestify\Http\Controllers\Auth\ResetPasswordController;
use Binaryk\LaravelRestify\Http\Controllers\Auth\VerifyController;
use Binaryk\LaravelRestify\Http\Middleware\EnsureJsonApiHeaderMiddleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionException;
use RuntimeException;

class RestifyApplicationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->authorization();
        $this->repositories();
        $this->authRoutes();
    }

    /**
     * Register the application's Rest resources.
     *
     * @return void
     * @throws ReflectionException
     */
    protected function repositories(): void
    {
        if ((false === is_dir(app_path('Restify'))) && ! mkdir($concurrentDirectory = app_path('Restify')) && ! is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        Restify::repositoriesFrom(app_path('Restify'));
    }

    /**
     * Configure the Restify authorization services.
     *
     * @return void
     */
    protected function authorization(): void
    {
        $this->gate();

        /*
         * Adding an auth callback. This callback will be verified in the AuthorizeRestify middleware,
         * which is the last middleware in the middleware list from the configuration.
         */
        Restify::auth(function ($request) {
            return app()->environment('local') ||
                Gate::check('viewRestify', [$request->user()]);
        });
    }

    /**
     * Register the Restify gate.
     *
     * This gate determines who can access Restify in non-local environments.
     *
     * This gate is checked in `authorization` method above and it should be overrided in the child
     * service provider
     *
     * @return void
     */
    protected function gate(): void
    {
        Gate::define('viewRestify', function ($user) {
            return in_array($user->email, [
                //
            ], true);
        });
    }

    protected function authRoutes(): void
    {
        Route::macro('restifyAuth', function ($prefix = '/') {
            Route::group([
                'prefix' => $prefix,
                'middleware' => [EnsureJsonApiHeaderMiddleware::class],
            ], function () {
                Route::post('register', RegisterController::class)
                    ->name('restify.register');

                Route::post('login', LoginController::class)
                    ->middleware('throttle:6,1')
                    ->name('restify.login');

                Route::post('verify/{id}/{hash}', VerifyController::class)
                    ->middleware('throttle:6,1')
                    ->name('restify.verify');

                Route::post('forgotPassword', ForgotPasswordController::class)
                    ->middleware('throttle:6,1')
                    ->name('restify.forgotPassword');

                Route::post('resetPassword', ResetPasswordController::class)
                    ->middleware('throttle:6,1')
                    ->name('restify.resetPassword');
            });
        });
    }
}
