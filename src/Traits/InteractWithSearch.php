<?php

namespace Binaryk\LaravelRestify\Traits;

use Binaryk\LaravelRestify\Eager\RelatedCollection;
use Binaryk\LaravelRestify\Filters\AdvancedFiltersCollection;
use Binaryk\LaravelRestify\Filters\Filter;
use Binaryk\LaravelRestify\Filters\MatchesCollection;
use Binaryk\LaravelRestify\Filters\MatchFilter;
use Binaryk\LaravelRestify\Filters\SearchableFilter;
use Binaryk\LaravelRestify\Filters\SortableFilter;
use Binaryk\LaravelRestify\Filters\SortCollection;
use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Repositories\Repository;
use Illuminate\Support\Collection;

trait InteractWithSearch
{
    use AuthorizableModels;

    public static $defaultPerPage = 15;

    public static $defaultRelatablePerPage = 15;

    public static function searchables(): array
    {
        return empty(static::$search)
            ? [static::newModel()->getKeyName()]
            : static::$search;
    }

    public static function withs(): array
    {
        return static::$withs ?? [];
    }

    public static function related(): array
    {
        return static::$related ?? [];
    }

    public static function collectRelated(): RelatedCollection
    {
        return RelatedCollection::make(static::related());
    }

    public static function matches(): array
    {
        return empty(static::$match)
            ? [static::newModel()->getKeyName()]
            : static::$match;
    }

    public static function sorts(): array
    {
        return empty(static::$sort)
            ? [static::newModel()->getQualifiedKeyName()]
            : static::$sort;
    }

    public static function collectSorts(RestifyRequest $request, Repository $repository): SortCollection
    {
        return SortCollection::make(explode(',', $request->input('sort', '')))
            ->normalize()
            ->hydrateDefinition($repository)
            ->authorized($request)
            ->inRepository($request, $repository)
            ->hydrateRepository($repository);
    }

    public static function collectMatches(RestifyRequest $request, Repository $repository): MatchesCollection
    {
        return MatchesCollection::make($repository::matches())
            ->normalize()
            ->authorized($request)
            ->inQuery($request)
            ->hydrateDefinition($request, $repository);
    }

    public static function collectFilters($type): Collection
    {
        $filters = collect([
            SearchableFilter::uriKey() => static::searchables(),
            MatchFilter::uriKey() => static::matches(),
            SortableFilter::uriKey() => static::sorts(),
        ])->get($type);

        /** * @var string $base */
        $base = collect([
            SearchableFilter::uriKey() => SearchableFilter::class,
            MatchFilter::uriKey() => MatchFilter::class,
            SortableFilter::uriKey() => SortableFilter::class,
        ])->get($type);

        if (! is_subclass_of($base, Filter::class)) {
            return collect([]);
        }

        return collect($filters)->map(function ($type, $column) use ($base) {
            if (is_numeric($column)) {
                /*
                 * This will handle for example searchables/sortables, where the definition is:
                 * $search = ['title']
                 * */
                $column = $type;
                $type = null;
            }

            return $type instanceof Filter
                ? tap($type, fn ($filter) => $filter->column = $filter->column ?? $column)
                : tap(new $base, function (Filter $filter) use ($column, $type) {
                    $filter->type = $type ? $type : 'value';
                    $filter->column = $column;
                });
        })->values();
    }

    public function collectAdvancedFilters(RestifyRequest $request): AdvancedFiltersCollection
    {
        return AdvancedFiltersCollection::make($this->filters($request))->authorized($request);
    }

    abstract public function filters(RestifyRequest $request): array;
}
