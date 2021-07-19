<?php

namespace Binaryk\LaravelRestify\Tests\Controllers;

use Binaryk\LaravelRestify\Filters\SortableFilter;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\PostRepository;
use Binaryk\LaravelRestify\Tests\IntegrationTest;
use Illuminate\Testing\Fluent\AssertableJson;

class RepositoryFilterControllerTest extends IntegrationTest
{
    public function test_available_filters_contains_matches_sortables_searches(): void
    {
        PostRepository::$match = [
            'title' => 'text',
        ];

        PostRepository::$sort = [
            'title',
        ];

        PostRepository::$search = [
            'id',
            'title',
        ];

        $this->withoutExceptionHandling();
        $this->getJson('posts/filters?include=matches,sortables,searchables')
            ->dump()
            // 5 custom filters
            // 1 match filter
            // 1 sort
            // 2 searchable
            ->assertJson(
                fn (AssertableJson $json) => $json
                ->where('data.0.rules.is_active', 'bool')
                ->where('data.4.type', 'text')
                ->where('data.4.column', 'title')
                ->where('data.5.type', 'value')
                ->where('data.5.column', 'title')
                ->where('data.6.type', 'value')
                ->where('data.6.column', 'id')
                ->etc()
            )
            ->assertJsonCount(8, 'data');
    }

    public function test_available_filters_returns_only_matches_sortables_searches(): void
    {
        PostRepository::$match = [
            'title' => 'text',
        ];

        PostRepository::$sort = [
            'title' => SortableFilter::make()->setColumn('posts.title'),
        ];

        PostRepository::$search = [
            'id',
            'title',
        ];

        $this->getJson('posts/filters?only=matches,sortables,searchables')
            ->assertJsonCount(4, 'data');

        $this->getJson('posts/filters?only=matches')
            ->assertJsonCount(1, 'data');

        $this->getJson('posts/filters?only=sortables')->assertJsonCount(1, 'data');

        $this->getJson('posts/filters?only=searchables')
            ->assertJsonCount(2, 'data');
    }
}
