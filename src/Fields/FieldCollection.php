<?php

namespace Binaryk\LaravelRestify\Fields;

use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Repositories\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FieldCollection extends Collection
{
    public function authorized(Request $request): self
    {
        return $this->filter(function (OrganicField $field) use ($request) {
            return $field->authorize($request);
        })->values();
    }

    public function authorizedUpdate(Request $request): self
    {
        return $this->filter(function (OrganicField $field) use ($request) {
            return $field->authorizedToUpdate($request);
        })->values();
    }

    public function authorizedPatch(Request $request): self
    {
        return $this->filter(function (OrganicField $field) use ($request) {
            return $field->authorizedToPatch($request);
        })->values();
    }

    public function authorizedUpdateBulk(Request $request): self
    {
        return $this->filter(function (OrganicField $field) use ($request) {
            return $field->authorizedToUpdateBulk($request);
        })->values();
    }

    public function authorizedStore(Request $request): self
    {
        return $this->filter(function (OrganicField $field) use ($request) {
            return $field->authorizedToStore($request);
        })->values();
    }

    public function resolve($repository): self
    {
        return $this->each(function ($field) use ($repository) {
            $field->resolve($repository);
        });
    }

    public function forIndex(RestifyRequest $request, $repository): self
    {
        return $this
            ->filter(fn (Field $field) => ! $field instanceof EagerField)
            ->filter(function (Field $field) use ($repository, $request) {
                return $field->isShownOnIndex($request, $repository);
            })->values();
    }

    public function forShow(RestifyRequest $request, $repository): self
    {
        return $this
            ->filter(fn (Field $field) => ! $field instanceof EagerField)
            ->filter(function (Field $field) use ($repository, $request) {
                return $field->isShownOnShow($request, $repository);
            })->values();
    }

    public function forStore(RestifyRequest $request, $repository): self
    {
        return $this
            ->filter(fn (Field $field) => ! $field instanceof EagerField)
            ->filter(function (Field $field) use ($repository, $request) {
                return $field->isShownOnStore($request, $repository);
            })->values();
    }

    public function forStoreBulk(RestifyRequest $request, $repository): self
    {
        return $this->filter(function (Field $field) use ($repository, $request) {
            return $field->isShownOnStoreBulk($request, $repository);
        })->values();
    }

    public function forUpdate(RestifyRequest $request, $repository): self
    {
        return $this
            ->filter(fn (Field $field) => ! $field instanceof EagerField)
            ->filter(function (Field $field) use ($repository, $request) {
                return $field->isShownOnUpdate($request, $repository);
            })->values();
    }

    public function forUpdateBulk(RestifyRequest $request, $repository): self
    {
        return $this->filter(function (Field $field) use ($repository, $request) {
            return $field->isShownOnUpdateBulk($request, $repository);
        })->values();
    }

    public function filterForManyToManyRelations(RestifyRequest $request): self
    {
        return $this->filter(function ($field) {
            return $field instanceof BelongsToMany || $field instanceof MorphToMany;
        })->filter(fn (EagerField $field) => $field->authorize($request));
    }

    public function forEager(RestifyRequest $request, Repository $repository): self
    {
        return $this
            ->filter(fn (Field $field) => $field instanceof EagerField)
            ->filter(fn (Field $field) => $field->authorize($request))
            ->unique();
    }

    public function forBelongsTo(RestifyRequest $request): self
    {
        return $this
            ->filter(fn (Field $field) => $field instanceof BelongsTo)
            ->unique();
    }

    public function setRepository(Repository $repository): self
    {
        return $this->each(fn (Field $field) => $field->setRepository($repository));
    }

    public function findFieldByAttribute($attribute, $default = null)
    {
        foreach ($this->items as $field) {
            if (isset($field->attribute) && $field->attribute === $attribute) {
                return $field;
            }
        }

        return null;
    }
}
