<?php

namespace Binaryk\LaravelRestify\Tests;

use Binaryk\LaravelRestify\LaravelRestifyServiceProvider;
use Binaryk\LaravelRestify\Repositories\Repository;
use Binaryk\LaravelRestify\Restify;
use Binaryk\LaravelRestify\RestifyApplicationServiceProvider;
use Binaryk\LaravelRestify\Tests\Fixtures\Company\CompanyRepository;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\Post;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\PostRepository;
use Binaryk\LaravelRestify\Tests\Fixtures\Role\RoleRepository;
use Binaryk\LaravelRestify\Tests\Fixtures\User\User;
use Binaryk\LaravelRestify\Tests\Fixtures\User\UserRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use JetBrains\PhpStorm\Pure;
use Mockery;
use Orchestra\Testbench\TestCase;

abstract class IntegrationTest extends TestCase
{
    protected Mockery\MockInterface | User $authenticatedAs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadRepositories()
            ->loadMigrations();

        $this->app['config']->set('config.auth.user_model', User::class);

        $this->app->register(RestifyApplicationServiceProvider::class);

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Binaryk\\LaravelRestify\\Tests\\Factories\\' . class_basename($modelName) . 'Factory'
        );

        Restify::$authUsing = static function () {
            return true;
        };
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        Repository::clearResolvedInstances();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelRestifyServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('restify.base', '/');

        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        include_once __DIR__ . '/../database/migrations/create_action_logs_table.php.stub';
        (new \CreateActionLogsTable())->up();
    }

    protected function loadMigrations(): self
    {
        $this->loadMigrationsFrom([
            '--database' => 'sqlite',
            '--path' => realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Migrations'),
        ]);

        return $this;
    }

    public function loadRepositories(): self
    {
        Restify::repositories([
            UserRepository::class,
            PostRepository::class,
            CompanyRepository::class,
            \Binaryk\LaravelRestify\Tests\Fixtures\Post\PostWithHiddenFieldRepository::class,
            RoleRepository::class,
        ]);

        return $this;
    }

    protected function authenticate(Authenticatable $user = null)
    {
        $this->actingAs($this->authenticatedAs = $user ?? Mockery::mock(Authenticatable::class));

        if (is_null($user)) {
            $this->authenticatedAs->shouldReceive('getAuthIdentifier')->andReturn(1);
            $this->authenticatedAs->shouldReceive('getKey')->andReturn(1);
        }

        return $this;
    }

    public function mockUsers($count = 1, array $predefinedEmails = []): Collection
    {
        return Collection::times($count, fn ($i) => User::factory()->create())
            ->merge(collect($predefinedEmails)->each(fn (string $email) => User::factory()->create([
                'email' => $email,
            ])))
            ->shuffle();
    }

    public function mockPosts($userId = null, $count = 1): Collection
    {
        return Collection::times($count, fn () => Post::factory()->create([
            'user_id' => $userId,
        ]))->shuffle();
    }

    protected function mockPost(array $attributes = []): Post
    {
        return Post::factory()->create($attributes);
    }

    public function getTempDirectory($suffix = ''): string
    {
        return __DIR__ . '/TestSupport/temp' . ($suffix === '' ? '' : '/' . $suffix);
    }

    #[Pure] public function getMediaDirectory($suffix = ''): string
    {
        return $this->getTempDirectory() . '/media' . ($suffix === '' ? '' : '/' . $suffix);
    }

    #[Pure] public function getTestFilesDirectory($suffix = ''): string
    {
        return $this->getTempDirectory() . '/testfiles' . ($suffix === '' ? '' : '/' . $suffix);
    }

    #[Pure] public function getTestJpg(): string
    {
        return $this->getTestFilesDirectory('test.jpg');
    }
}
