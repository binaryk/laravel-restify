<?php

namespace Binaryk\LaravelRestify\Repositories\Casts;

use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

abstract class RepositoryCast
{
    abstract public static function fromBuilder(RestifyRequest $request, Builder $builder): Collection;

    abstract public static function fromRelation(RestifyRequest $request, Relation $relation): Collection;
}
