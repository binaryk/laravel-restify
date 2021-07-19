<?php

namespace Binaryk\LaravelRestify\Http\Controllers;

use Binaryk\LaravelRestify\Http\Requests\RepositoryPatchRequest;
use Binaryk\LaravelRestify\Repositories\Repository;

class RepositoryPatchController extends RepositoryController
{
    public function __invoke(RepositoryPatchRequest $request)
    {
        $model = $request->modelQuery()->lockForUpdate()->firstOrFail();

        /** * @var Repository $repository */
        $repository = $request->repositoryWith($model);

        return $repository->allowToPatch($request)->patch($request, request('repositoryId'));
    }
}
