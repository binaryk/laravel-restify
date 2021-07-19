<?php

namespace Binaryk\LaravelRestify\Tests\Controllers;

use Binaryk\LaravelRestify\Fields\Field;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\Post;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\PostRepository;
use Binaryk\LaravelRestify\Tests\IntegrationTest;

class RepositoryPatchControllerTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function test_partial_update_doesnt_validate_other_fields(): void
    {
        $post = Post::factory()->create([
            'title' => 'Initial title.',
        ]);

        PostRepository::partialMock()
            ->shouldReceive('fields')
            ->andReturn([
                field('title')->rules('required'),
                field('description')->rules('required'),
            ]);

        $this->patchJson(PostRepository::to($post->id), [
            'title' => 'Updated title.',
        ])->assertOk();

        self::assertEquals('Updated title.', $post->fresh()->title);
    }
}
