<?php

namespace Binaryk\LaravelRestify\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

class PublishAuthControllerCommand extends Command
{
    protected $name = 'restify:publish-controllers';

    protected $description = 'Publish controllers from package to local project';

    public function handle()
    {
        $this
            ->publishControllers()
            ->publishBlades()
            ->publishEmails()
            ->registerRutes();

        $this->info('Restify Controllers & Emails published successfully');
    }

    /**
     *
     * @return $this
     */
    public function publishControllers(): self
    {
        $path = 'Http/Controllers/Restify/Auth/';
        $stubDirectory = '/../Commands/stubs/Auth';
        $format = '.php';

        $this->checkDirectory($path)
            ->copyDirectory($path, $stubDirectory, $format);

        return $this;
    }

    /**
     *
     * @return $this
     */
    public function publishBlades(): self
    {
        $path = '../resources/views/Restify/Auth/';
        $stubDirectory = '/../Commands/stubs/Blades';
        $format = '.blade.php';

        $this->checkDirectory($path)
            ->copyDirectory($path, $stubDirectory, $format);

        return $this;
    }

    /**
     *
     * @return $this
     */
    public function publishEmails(): self
    {
        $path = 'Mail/Restify/Auth/';
        $stubDirectory = '/../Commands/stubs/Email';
        $format = '.php';

        $this->checkDirectory($path)
            ->copyDirectory($path, $stubDirectory, $format);

        return $this;
    }

    /**
     *
     * @param string $path
     * @return $this
     */
    public function checkDirectory(string $path): self
    {
        if (! is_dir($directory = app_path($path))) {
            mkdir($directory, 0755, true);
        }

        return $this;
    }

    /**
     *
     * @param string $path
     * @param string $stubDirectory
     * @param string $format
     * @return $this
     */
    protected function copyDirectory(string $path, string $stubDirectory, string $format): self
    {
        $filesystem = new Filesystem;

        collect($filesystem->allFiles(__DIR__ . $stubDirectory))
            ->each(function (SplFileInfo $file) use ($filesystem, $path, $format, $stubDirectory) {
                $filesystem->copy(
                    $file->getPathname(),
                    $fullPath = app_path($path . Str::replaceLast('.stub', $format, $file->getFilename()))
                );

                $this->setNamespace($stubDirectory, $file->getFilename(), $path, $fullPath);
            });

        return $this;
    }

    /**
     *
     * @param string $stubDirectory
     * @param string $fileName
     * @param string $path
     * @param string $fullPath
     * @return string
     */
    protected function setNamespace(string $stubDirectory, string $fileName, string $path, string $fullPath): string
    {
        $path = substr(str_replace('/', '\\', $path), 0, -1);

        return file_put_contents($fullPath, str_replace(
            '{{namespace}}',
            $this->laravel->getNamespace() . $path,
            file_get_contents(__DIR__ . $stubDirectory . '/' . $fileName)
        ));
    }

    /**
     *
     * @return $this
     */
    protected function registerRutes(): self
    {
        $pathProvider = 'Providers/RestifyServiceProvider.php';
        $routeStub = __DIR__ . '/stubs/Routes/routes.stub';

        if (! file_exists(app_path($pathProvider))) {
            $this->callSilent('restify:setup');
        }

        file_put_contents(app_path($pathProvider), str_replace(
            "use Binaryk\LaravelRestify\RestifyApplicationServiceProvider;" . PHP_EOL,
            "use Binaryk\LaravelRestify\RestifyApplicationServiceProvider;" . PHP_EOL .
            "use App\Http\Controllers\Restify\Auth\RegisterController;" . PHP_EOL .
            "use App\Http\Controllers\Restify\Auth\ForgotPasswordController;" . PHP_EOL .
            "use App\Http\Controllers\Restify\Auth\LoginController;" . PHP_EOL .
            "use App\Http\Controllers\Restify\Auth\ResetPasswordController;" . PHP_EOL .
            "use Illuminate\Support\Facades\Route;" . PHP_EOL .
            "use App\Http\Controllers\Restify\Auth\VerifyController;" . PHP_EOL,
            file_get_contents(app_path($pathProvider))
        ));

        file_put_contents(app_path($pathProvider), str_replace(
            "public function register()
    {
   ",
            file_get_contents($routeStub),
            file_get_contents(app_path($pathProvider))
        ));

        return $this;
    }
}
