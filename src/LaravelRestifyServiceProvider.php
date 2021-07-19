<?php

namespace Binaryk\LaravelRestify;

use Binaryk\LaravelRestify\Commands\ActionCommand;
use Binaryk\LaravelRestify\Commands\BaseRepositoryCommand;
use Binaryk\LaravelRestify\Commands\DevCommand;
use Binaryk\LaravelRestify\Commands\FilterCommand;
use Binaryk\LaravelRestify\Commands\PolicyCommand;
use Binaryk\LaravelRestify\Commands\PublishAuthControllerCommand;
use Binaryk\LaravelRestify\Commands\Refresh;
use Binaryk\LaravelRestify\Commands\RepositoryCommand;
use Binaryk\LaravelRestify\Commands\SetupCommand;
use Binaryk\LaravelRestify\Commands\StoreCommand;
use Binaryk\LaravelRestify\Commands\StubCommand;
use Binaryk\LaravelRestify\Events\AddedRepositories;
use Binaryk\LaravelRestify\Http\Middleware\RestifyInjector;
use Binaryk\LaravelRestify\Listeners\MountMissingRepositories;
use Binaryk\LaravelRestify\Repositories\Repository;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class LaravelRestifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SetupCommand::class,
                PolicyCommand::class,
                BaseRepositoryCommand::class,
                Refresh::class,
                StubCommand::class,
                PublishAuthControllerCommand::class,
            ]);
            $this->registerPublishing();
        }

        $this->listeners();

        $this->app->make(HttpKernel::class)->pushMiddleware(RestifyInjector::class);
    }

    public function register(): void
    {
        Repository::clearBootedRepositories();

        // Register the main class to use with the facade
        $this->app->singleton('laravel-restify', function () {
            return new Restify;
        });

        $this->commands([
            RepositoryCommand::class,
            ActionCommand::class,
            StoreCommand::class,
            FilterCommand::class,
            DevCommand::class,
        ]);
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/Commands/stubs/RestifyServiceProvider.stub' => app_path('Providers/RestifyServiceProvider.php'),
        ], 'restify-provider');

        $this->publishes([
            __DIR__.'/../config/config.php' => config_path('restify.php'),
        ], 'restify-config');

        $migrationFileName = 'create_action_logs_table.php.stub';
        if (! $this->migrationFileExists($migrationFileName)) {
            $this->publishes([
                __DIR__."/../database/migrations/{$migrationFileName}" => database_path('migrations/'.date(
                    'Y_m_d_His',
                    time()
                ).'_'.Str::before($migrationFileName, '.stub')),
            ], 'restify-migrations');
        }

        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'restify');
    }

    public static function migrationFileExists(string $migrationFileName): bool
    {
        $len = strlen($migrationFileName);
        foreach (glob(database_path('migrations/*.php')) as $filename) {
            if ((substr($filename, -$len) === $migrationFileName)) {
                return true;
            }
        }

        return false;
    }

    private function listeners(): void
    {
        Event::listen(
            AddedRepositories::class,
            [MountMissingRepositories::class, 'handle'],
        );
    }
}
