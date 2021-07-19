<?php

namespace Binaryk\LaravelRestify\Tests\Fields;

use Binaryk\LaravelRestify\Fields\BelongsTo;
use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Repositories\Repository;
use Binaryk\LaravelRestify\Restify;
use Binaryk\LaravelRestify\Tests\Factories\PostFactory;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\Post;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\PostPolicy;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\PostRepository;
use Binaryk\LaravelRestify\Tests\Fixtures\User\User;
use Binaryk\LaravelRestify\Tests\Fixtures\User\UserPolicy;
use Binaryk\LaravelRestify\Tests\Fixtures\User\UserRepository;
use Binaryk\LaravelRestify\Tests\IntegrationTest;
use Illuminate\Support\Facades\Gate;

class BelongsToFieldTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticate();

        Restify::repositories([
            PostWithUserRepository::class,
        ]);

        unset($_SERVER['restify.post.store']);
        unset($_SERVER['restify.post.allowRestify']);
        unset($_SERVER['restify.users.show']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Repository::clearResolvedInstances();
    }

    public function test_present_on_show_when_specified_related(): void
    {
        $post = PostFactory::one();

        PostRepository::partialMock()
            ->shouldReceive('related')
            ->andReturn([
                'user' => BelongsTo::make('user', UserRepository::class),
            ]);

        $this->getJson(PostRepository::to($post->id, [
            'related' => 'user',
        ]))
            ->assertJsonStructure([
                'data' => [
                    'relationships' => [
                        'user' => [
                            'id',
                            'type',
                            'attributes',
                        ],
                    ],
                ],
            ]);

        $relationships = $this->getJson(PostRepository::to($post->id))
            ->json('data.relationships');

        $this->assertNull($relationships);
    }

    public function test_unauthorized_see_relationship(): void
    {
        $_SERVER['restify.users.show'] = false;

        Gate::policy(User::class, UserPolicy::class);

        tap(Post::factory()->create([
            'user_id' => User::factory(),
        ]), function ($post) {
            $this->getJson(PostWithUserRepository::uriKey()."/{$post->id}?related=user")
                ->assertForbidden();
        });
    }

    public function test_dont_show_key_when_nullable_related()
    {
        $_SERVER['restify.users.show'] = true;

        Gate::policy(User::class, UserPolicy::class);

        tap(Post::factory()->create([
            'user_id' => null,
        ]), function ($post) {
            $this->getJson(PostWithUserRepository::uriKey()."/{$post->id}?related=user")
                ->assertJsonFragment([
                    'user' => null,
                ])
                ->assertOk();
        });
    }

    public function test_field_used_when_storing()
    {
        tap(User::factory()->create(), function ($user) {
            $this->postJson(PostWithUserRepository::uriKey(), [
                'title' => 'Create post with owner.',
                'user' => $user->id,
            ])->assertCreated();
        });
    }

    public function test_unauthorized_via_callback_models_cannot_be_attached(): void
    {
        PostWithUserRepository::partialMock()
            ->shouldReceive('fields')
            ->andReturn([
                field('title'),
                BelongsTo::make('user', UserRepository::class)
                    ->canAttach(function ($request, $repository, $model) {
                        $this->assertInstanceOf(RestifyRequest::class, $request);
                        $this->assertInstanceOf(Repository::class, $repository);
                        $this->assertInstanceOf(User::class, $model);

                        return false;
                    }),
            ]);

        tap(User::factory()->create(), function ($user) {
            $this->postJson(PostWithUserRepository::uriKey(), [
                'title' => 'Create post with owner.',
                'user' => $user->id,
            ])->assertForbidden();
        });
    }

    public function test_unauthorized_via_policy_models_cannot_be_attached()
    {
        $_SERVER['restify.post.allowAttachUser'] = false;

        Gate::policy(Post::class, PostPolicy::class);

        $this->assertDatabaseCount('posts', 0);

        tap(User::factory()->create(), function ($user) {
            $this->postJson(PostWithUserRepository::uriKey(), [
                'title' => 'Create post with owner.',
                'user' => $user->id,
            ])->assertForbidden();

            $this->assertDatabaseCount('posts', 0);

            $_SERVER['restify.post.allowAttachUser'] = true;

            $this->postJson(PostWithUserRepository::uriKey(), [
                'title' => 'Create post with owner.',
                'user' => $user->id,
            ])->assertCreated();
        });

        $this->assertDatabaseCount('posts', 1);
    }

    public function test_unauthorized_without_authorization_method_defined_to_attach_models()
    {
        Gate::policy(Post::class, PostPolicyWithoutMethod::class);

        tap(User::factory()->create(), function ($user) {
            $this->postJson(PostWithUserRepository::uriKey(), [
                'title' => 'Create post with owner.',
                'user' => $user->id,
            ])->assertForbidden();
        });
    }

    public function test_field_used_when_updating()
    {
        tap(Post::factory()->create([
            'user_id' => User::factory(),
        ]), function ($post) {
            $newOwner = User::factory()->create();
            $this->putJson(PostWithUserRepository::uriKey()."/{$post->id}", [
                'title' => 'Can change post owner.',
                'user' => $newOwner->id,
            ])->assertOk();

            $this->assertSame($post->fresh()->user->id, $newOwner->id);
        });
    }

    public function test_unauthorized_via_policy_when_updating()
    {
        $_SERVER['restify.post.allowAttachUser'] = false;

        Gate::policy(Post::class, PostPolicy::class);

        tap(Post::factory()->create([
            'user_id' => User::factory(),
        ]), function ($post) {
            $firstOwnerId = $post->user->id;
            $newOwner = User::factory()->create();
            $this->putJson(PostWithUserRepository::uriKey()."/{$post->id}", [
                'title' => 'Can change post owner.',
                'user' => $newOwner->id,
            ])->assertForbidden();

            $this->assertSame($post->fresh()->user->id, $firstOwnerId);
        });
    }
}

class PostWithUserRepository extends Repository
{
    public static $model = Post::class;

    public static function related(): array
    {
        return [
            'user' => BelongsTo::make('user', UserRepository::class),
        ];
    }

    public function fields(RestifyRequest $request): array
    {
        return [
            field('title'),

            BelongsTo::make('user', UserRepository::class),
        ];
    }
}

class PostPolicyWithoutMethod
{
    public function allowRestify($user)
    {
        return true;
    }

    public function store($user)
    {
        return true;
    }
}
