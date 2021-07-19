<?php

namespace Binaryk\LaravelRestify\Tests\Controllers;

use Binaryk\LaravelRestify\Fields\Field;
use Binaryk\LaravelRestify\Models\ActionLog;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\Post;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\PostPolicy;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\PostRepository;
use Binaryk\LaravelRestify\Tests\IntegrationTest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\Fluent\AssertableJson;

class RepositoryUpdateControllerTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function test_basic_update_works()
    {
        $post = Post::factory()->create();

        $this->putJson('posts/'.$post->id, [
            'title' => 'Updated title',
        ])->assertOk();

        $this->assertEquals('Updated title', Post::find($post->id)->title);
    }

    public function test_put_works(): void
    {
        $post = Post::factory()->create();

        $this->putJson('posts/'.$post->id, [
            'title' => 'Updated title',
        ])->assertOk();

        $this->assertEquals('Updated title', Post::find($post->id)->title);
    }

    public function test_unauthorized_to_update(): void
    {
        Gate::policy(Post::class, PostPolicy::class);

        $post = Post::factory()->create();

        $_SERVER['restify.post.update'] = false;

        $this->putJson('posts/'.$post->id, [
            'title' => 'Updated title',
        ])->assertStatus(403);
    }

    public function test_cannot_update_unauthorized_fields(): void
    {
        PostRepository::partialMock()
            ->shouldReceive('fieldsForUpdate')
            ->andreturn([
                Field::new('image')->canUpdate(fn () => false),

                Field::new('title'),
            ]);

        $this->putJson(PostRepository::to(Post::factory()->create([
            'image' => null,
            'title' => 'Initial',
        ])->id), [
            'image' => 'avatar.png',
            'title' => $updated = 'Updated',
        ])
            ->assertJson(
                fn (AssertableJson $json) => $json
                ->where('data.attributes.title', $updated)
                ->where('data.attributes.image', null)
                ->etc()
            );
    }

    public function test_cannot_update_readonly_fields(): void
    {
        PostRepository::partialMock()
            ->shouldReceive('fieldsForUpdate')
            ->andreturn([
                Field::new('image')->readonly(),

                Field::new('title'),
            ]);

        $this->putJson(PostRepository::to(Post::factory()->create([
            'image' => null,
            'title' => 'Initial',
        ])->id), [
            'image' => 'avatar.png',
            'title' => $updated = 'Updated',
        ])
            ->assertJson(
                fn (AssertableJson $json) => $json
                ->where('data.attributes.title', $updated)
                ->where('data.attributes.image', null)
                ->etc()
            );
    }

    public function test_updating_repository_log_action(): void
    {
        $this->authenticate();

        $post = Post::factory()->create([
            'title' => 'Original',
        ]);

        $this->postJson("posts/$post->id", $data = [
            'title' => 'Title changed',
        ])->assertSuccessful();

        $this->assertDatabaseHas('action_logs', [
            'user_id' => $this->authenticatedAs->getAuthIdentifier(),
            'name' => ActionLog::ACTION_UPDATED,
            'actionable_type' => Post::class,
            'actionable_id' => (string) $post->id,
        ]);

        $log = ActionLog::latest()->first();

        $this->assertSame($data, $log->changes);
        $this->assertSame(['title' => 'Original'], $log->original);
    }
}
