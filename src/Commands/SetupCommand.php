<?php

namespace Binaryk\LaravelRestify\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SetupCommand extends Command
{
    protected $signature = 'restify:setup';

    protected $description = 'Should be run when you firstly install the package. It will setup everything for you.';

    public function handle()
    {
        $this->comment('Publishing Restify Service Provider...');
        $this->callSilent('vendor:publish', ['--tag' => 'restify-provider']);

        $this->comment('Publishing Restify config...');
        $this->call('vendor:publish', [
            '--tag' => 'restify-config',
        ]);

        $this->comment('Publishing Restify migrations...');
        $this->call('vendor:publish', [
            '--tag' => 'restify-migrations',
        ]);

        $this->registerRestifyServiceProvider();

        $this->comment('Generating User Repository...');
        $this->callSilent('restify:repository', ['name' => 'User']);
        copy(__DIR__.'/stubs/user-repository.stub', app_path('Restify/UserRepository.php'));

        $this->setAppNamespace();

        $this->info('Restify setup successfully.');
    }

    /**
     * Register the Restify service provider in the application configuration file.
     *
     * @return void
     */
    protected function registerRestifyServiceProvider()
    {
        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());

        file_put_contents(config_path('app.php'), str_replace(
            "{$namespace}\\Providers\EventServiceProvider::class,".PHP_EOL,
            "{$namespace}\\Providers\EventServiceProvider::class,".PHP_EOL."        {$namespace}\Providers\RestifyServiceProvider::class,".PHP_EOL,
            file_get_contents(config_path('app.php'))
        ));
    }

    /**
     * Set the proper application namespace on the installed files.
     *
     * @return void
     */
    protected function setAppNamespace()
    {
        $namespace = $this->laravel->getNamespace();

        $this->setAppNamespaceOn(app_path('Restify/UserRepository.php'), $namespace);
        $this->setAppNamespaceOn(app_path('Providers/RestifyServiceProvider.php'), $namespace);
    }

    /**
     * Set the namespace on the given file.
     *
     * @param  string  $file
     * @param  string  $namespace
     * @return void
     */
    protected function setAppNamespaceOn($file, $namespace)
    {
        file_put_contents($file, str_replace(
            'App\\',
            $namespace,
            file_get_contents($file)
        ));
    }
}
