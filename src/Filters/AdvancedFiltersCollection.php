<?php

namespace Binaryk\LaravelRestify\Filters;

use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Repositories\Repository;
use Illuminate\Support\Collection;

class AdvancedFiltersCollection extends Collection
{
    public function authorized(RestifyRequest $request): self
    {
        return $this->filter(fn (Filter $filter) => $filter->authorizedToSee($request))->values();
    }

    public function apply(RestifyRequest $request, $query): self
    {
        return $this->each(fn (AdvancedFilter $filter) => $filter->filter($request, $query, $filter->dto->value));
    }

    public static function collectQueryFilters(RestifyRequest $request, Repository $repository): self
    {
        $filters = json_decode(base64_decode($request->input('filters')), true);

        $allowedFilters = $repository->collectAdvancedFilters($request);

        return static::make($filters)
            ->map(function (array $queryFilter) use ($allowedFilters, $request) {
                /** * @var AdvancedFilter $advancedFilter */
                $advancedFilter = $allowedFilters->first(fn (
                    AdvancedFilter $filter
                ) => $filter::uriKey() === data_get($queryFilter, 'key'));

                if (is_null($advancedFilter)) {
                    return null;
                }

                $advancedFilter = clone $advancedFilter;

                return $advancedFilter->resolve($request, $dto = new AdvancedFilterPayloadDto(
                    key: data_get($queryFilter, 'key'),
                    value: data_get($queryFilter, 'value'),
                ))->validatePayload($request, $dto);
            })
            ->filter();
    }
}
