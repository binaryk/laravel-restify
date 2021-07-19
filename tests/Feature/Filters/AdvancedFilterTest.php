<?php

namespace Binaryk\LaravelRestify\Tests\Feature\Filters;

use Binaryk\LaravelRestify\Filters\MatchFilter;
use Binaryk\LaravelRestify\Filters\SearchableFilter;
use Binaryk\LaravelRestify\Filters\SortableFilter;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\CreatedAfterDateFilter;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\InactiveFilter;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\Post;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\PostRepository;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\SelectCategoryFilter;
use Binaryk\LaravelRestify\Tests\Fixtures\User\UserRepository;
use Binaryk\LaravelRestify\Tests\IntegrationTest;
use Illuminate\Testing\Fluent\AssertableJson;

class AdvancedFilterTest extends IntegrationTest
{
    public function test_filters_can_have_definition(): void
    {
        PostRepository::$match = [
            'title' => 'string',
            'user_id' => MatchFilter::make()
                ->setType('int')
                ->setRelatedRepositoryKey(UserRepository::uriKey()),
        ];

        PostRepository::$search = [
            'title' => SearchableFilter::make()->setType('string'),
        ];

        PostRepository::$sort = [
            'id' => SortableFilter::make()->setType('int'),
            'title',
        ];

        $this->getJson('posts/filters?only=matches,searchables,sortables')
            ->assertJson(
                fn (AssertableJson $json) => $json
                ->where('data.1.repository.key', 'users')
                ->where('data.1.repository.label', 'Users')
                ->where('data.1.repository.display_key', 'id')
                ->etc()
            )
            ->assertJsonFragment([
                'key' => 'users',
            ]);
    }

    public function test_value_filter_doesnt_require_value(): void
    {
        Post::factory()->create([
            'title' => $expectedTitle = 'Inactive post.',
            'is_active' => false,
        ]);
        Post::factory()->create([
            'title' => 'Active post',
            'is_active' => true,
        ]);

        $filters = base64_encode(json_encode([
            [
                'key' => InactiveFilter::uriKey(),
                'value' => [
                    'is_active' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->getJson('posts?filters='.$filters)
            ->assertJson(
                fn (AssertableJson $json) => $json
                ->where('data.0.attributes.title', $expectedTitle)
                ->etc()
            )
            ->assertJsonCount(1, 'data');
    }

    public function test_select_filter_validates_payload(): void
    {
        Post::factory()->create(['category' => 'movie']);
        Post::factory()->create(['category' => 'article']);

        $filters = base64_encode(json_encode([
            [
                'key' => SelectCategoryFilter::uriKey(),
                'value' => [
                    'category' => 'wew',
                ],
            ],
            [
                'key' => SelectCategoryFilter::uriKey(),
                'value' => [
                    'category' => 'movie',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->getJson(PostRepository::to(null, ['filters' => $filters]))
            ->assertStatus(422);

        $filters = base64_encode(json_encode([
            [
                'key' => SelectCategoryFilter::uriKey(),
                'value' => [
                    'category' => 'article',
                ],
            ],
            [
                'key' => SelectCategoryFilter::uriKey(),
                'value' => [
                    'category' => 'movie',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->getJson(PostRepository::to(null, ['filters' => $filters]))
            ->assertJsonCount(0, 'data');
    }

    public function test_the_boolean_filter_is_applied(): void
    {
        Post::factory(1)->create(['is_active' => false]);
        Post::factory(2)->create(['is_active' => true]);

        $this
            ->getJson(PostRepository::uriKey().'/filters?include=matches')
            ->assertOk()
            ->assertJsonFragment($booleanFilter = [
                'key' => $key = 'active-booleans',
                'type' => 'boolean',
                'advanced' => true,
            ]);

        $filters = base64_encode(json_encode([
            [
                'key' => $key,
                'value' => [
                    'is_active' => false,
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->getJson('posts?filters='.$filters)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_select_filter_is_applied(): void
    {
        Post::factory()->create(['category' => 'movie']);
        Post::factory()->create(['category' => 'article']);

        $filters = base64_encode(json_encode([
            [
                'key' => SelectCategoryFilter::uriKey(),
                'value' => [
                    'category' => 'article',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->getJson(PostRepository::to(null, ['filters' => $filters]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_timestamp_filter_is_applied(): void
    {
        Post::factory()->create([
            'title' => 'Valid post',
            'created_at' => now()->addYear(),
        ]);
        Post::factory()->create(['created_at' => now()->subYear()]);

        $filters = base64_encode(json_encode([
            [
                'key' => UserRepository::uriKey(),
                'value' => now()->addWeek()->timestamp,
            ],
            [
                'key' => CreatedAfterDateFilter::uriKey(),
                'value' => [
                    'created_at' => now()->addWeek()->toDateString(),
                ],
            ],
        ]));

        $this->getJson('posts?filters='.$filters)
            ->assertOk()
            ->assertJson(
                fn (AssertableJson $json) => $json
                ->where('data.0.attributes.title', 'Valid post')
                ->count('data', 1)
                ->etc()
            );
    }
}
