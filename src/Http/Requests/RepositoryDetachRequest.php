<?php

namespace Binaryk\LaravelRestify\Http\Requests;

use Binaryk\LaravelRestify\Restify;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class RepositoryDetachRequest extends RestifyRequest
{
    public function detachRelatedModels(): Collection
    {
        $relatedRepository = $this->repository(
            Restify::repositoryForTable($table = $this->relatedRepository)::uriKey()
        );

        if (is_null($relatedRepository)) {
            abort(400, "Missing repository for the [$table] table");
        }

        return collect(Arr::wrap($this->input($this->relatedRepository)))
            ->map(fn ($id) => $relatedRepository->model()->newModelQuery()->whereKey($id)->first());
    }
}
