<?php

namespace Binaryk\LaravelRestify\Tests\Feature;

use Binaryk\LaravelRestify\Contracts\RestifySearchable;
use Binaryk\LaravelRestify\Fields\BelongsTo;
use Binaryk\LaravelRestify\Filters\MatchFilter;
use Binaryk\LaravelRestify\Filters\SearchableFilter;
use Binaryk\LaravelRestify\Filters\SortableFilter;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\Post;
use Binaryk\LaravelRestify\Tests\Fixtures\Post\PostRepository;
use Binaryk\LaravelRestify\Tests\Fixtures\User\User;
use Binaryk\LaravelRestify\Tests\Fixtures\User\UserRepository;
use Binaryk\LaravelRestify\Tests\Fixtures\User\VerifiedMatcher;
use Binaryk\LaravelRestify\Tests\IntegrationTest;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

class RepositorySearchServiceTest extends IntegrationTest
{
    public function test_can_match_date(): void
    {
        User::factory(2)->create([
            'created_at' => null,
        ]);

        User::factory(3)->create([
            'created_at' => '01-12-2020',
        ]);

        UserRepository::$match = [
            'created_at' => RestifySearchable::MATCH_DATETIME,
        ];

        $this->getJson('users?created_at=null')->assertJsonCount(2, 'data');

        $this->getJson('users?created_at=2020-12-01')->assertJsonCount(3, 'data');
    }

    public function test_can_match_array(): void
    {
        User::factory(4)->create();

        UserRepository::$match = [
            'id' => RestifySearchable::MATCH_ARRAY,
        ];

        $this->getJson('users?id=1,2,3')
            ->assertJsonCount(3, 'data');

        $this->getJson('users?-id=1,2,3')
            ->assertJsonCount(1, 'data');
    }

    public function test_can_match_using_json_api_recommendation(): void
    {
        User::factory(4)->create();

        UserRepository::$match = [
            'id' => RestifySearchable::MATCH_ARRAY,
        ];

        $this->getJson('users?filter[id]=1,2,3')
            ->assertJsonCount(3, 'data');

        $this->getJson('users?filter[-id]=1,2,3')
            ->assertJsonCount(1, 'data');
    }

    public function test_can_match_range(): void
    {
        User::factory(4)->create();

        UserRepository::$match = [
            'id' => RestifySearchable::MATCH_BETWEEN,
        ];

        $this->getJson('users?id=1,3')
            ->assertJsonCount(3, 'data');
    }

    public function test_can_match_datetime_interval(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'created_at' => now()->subMonth(),
        ]);
        $user->save();
        $user = User::factory()->create();

        $user->forceFill([
            'created_at' => now()->subWeek(),
        ]);
        $user->save();

        $user = User::factory()->create();

        $user->forceFill([
            'created_at' => now()->addMonth(),
        ]);
        $user->save();

        UserRepository::$match = [
            'created_at' => RestifySearchable::MATCH_BETWEEN,
        ];

        $twoMonthsAgo = now()->subMonths(2)->toISOString();
        $now = now()->toISOString();
        $this->getJson("users?created_at={$twoMonthsAgo},{$now}")
            ->assertJsonCount(2, 'data');

        $this->getJson("users?-created_at={$twoMonthsAgo},{$now}")
            ->assertJsonCount(1, 'data');
    }

    public function test_match_definition_hit_filter_method(): void
    {
        User::factory(4)->create();

        UserRepository::$match = [
            'id' => MatchFilter::make()->setType(RestifySearchable::MATCH_ARRAY),
        ];

        $this->getJson('users?-id=1,2,3')
            ->assertJsonCount(1, 'data');

        UserRepository::$match = [
            'id' => MatchFilter::make()->setType(RestifySearchable::MATCH_ARRAY),
        ];

        $this->getJson('users?id=1,2,3')
            ->assertJsonCount(3, 'data');
    }

    public function test_match_partially(): void
    {
        User::factory(2)->create([
            'name' => 'John Doe',
        ]);

        UserRepository::$match = [
            'name' => MatchFilter::make()->setType(RestifySearchable::MATCH_TEXT)->strict(),
        ];

        $this->getJson('users?name=John')->assertJsonCount(0, 'data');

        UserRepository::$match = [
            'name' => MatchFilter::make()->setType(RestifySearchable::MATCH_TEXT)->strict(),
        ];

        $this->getJson('users?-name=John')->assertJsonCount(2, 'data');

        UserRepository::$match = [
            'name' => MatchFilter::make()->setType(RestifySearchable::MATCH_TEXT)->partial(),
        ];

        $this->getJson('users?name=John')->assertJsonCount(2, 'data');

        UserRepository::$match = [
            'name' => MatchFilter::make()->setType(RestifySearchable::MATCH_TEXT)->partial(),
        ];
        $this->getJson('users?-name=John')->assertJsonCount(0, 'data');
    }

    public function test_can_search_using_filter_searchable_definition(): void
    {
        User::factory(4)->create([
            'name' => 'John Doe',
        ]);

        User::factory(4)->create([
            'name' => 'wew',
        ]);

        UserRepository::$search = [
            'name' => SearchableFilter::make(),
        ];

        $this->getJson('users?search=John')->assertJsonCount(4, 'data');
    }

    public function test_can_search_using_belongs_to_field(): void
    {
        $foreignUser = User::factory()->create([
            'name' => 'Curtis Dog',
        ]);

        Post::factory(4)->create([
            'user_id' => $foreignUser->id,
        ]);

        $john = User::factory()->create([
            'name' => 'John Doe',
        ]);

        Post::factory(2)->create([
            'user_id' => $john->id,
        ]);

        PostRepository::$related = [
            'user' => BelongsTo::make('user',  UserRepository::class)->searchable([
                'users.name',
            ]),
        ];

        $this->getJson('posts?search=John')
            ->assertJsonCount(2, 'data');
    }

    public function test_can_order_using_filter_sortable_definition(): void
    {
        User::factory()->create([
            'name' => 'Zoro',
        ]);

        User::factory()->create([
            'name' => 'Alisa',
        ]);

        UserRepository::$sort = [
            'name' => SortableFilter::make()->setColumn('name'),
        ];

        $this->assertSame('Alisa', $this->getJson('users?sort=name')
            ->json('data.0.attributes.name'));

        $this->assertSame('Zoro', $this->getJson('users?sort=name')
            ->json('data.1.attributes.name'));
        $this->assertSame('Zoro', $this->getJson('users?sort=-name')
            ->json('data.0.attributes.name'));
        $this->assertSame('Alisa', $this->getJson('users?sort=-name')
            ->json('data.1.attributes.name'));
    }

    public function test_can_match_closure(): void
    {
        User::factory(4)->create();

        UserRepository::$match = [
            'is_active' => function ($request, $query) {
                $this->assertInstanceOf(Request::class, $request);
                $this->assertInstanceOf(Builder::class, $query);
            },
        ];

        $this->getJson('users?is_active=true');
    }

    public function test_can_match_custom_matcher(): void
    {
        User::factory(1)->create([
            'email_verified_at' => now(),
        ]);

        User::factory(2)->create([
            'email_verified_at' => null,
        ]);

        UserRepository::$match = ['verified' => VerifiedMatcher::make()];
        $this->getJson('users?verified=true')->assertJsonCount(1, 'data');

        UserRepository::$match = ['verified' => VerifiedMatcher::make()];
        $this->getJson('users?verified=false')->assertJsonCount(2, 'data');
    }
}
