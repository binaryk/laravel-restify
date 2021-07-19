<?php

namespace Binaryk\LaravelRestify\Tests\Unit;

use Binaryk\LaravelRestify\Filters\MatchFilter;
use Binaryk\LaravelRestify\Tests\IntegrationTest;
use Illuminate\Testing\Fluent\AssertableJson;

class MatchableFilterTest extends IntegrationTest
{
    public function test_matchable_filter_has_key(): void
    {
        $filter = new class extends MatchFilter {
            public ?string $column = 'approved_at';
        };

        tap(
            AssertableJson::fromArray($filter->jsonSerialize()),
            function (AssertableJson $json) {
                $json
                    ->dump()
                    ->where('key', 'matches')
                    ->where('title', 'Approved At')
                    ->where('column', 'approved_at')
                    ->etc()
                ;
            }
        );
    }
}
