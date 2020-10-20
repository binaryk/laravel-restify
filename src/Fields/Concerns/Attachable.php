<?php

namespace Binaryk\LaravelRestify\Fields\Concerns;

use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Closure;
use DateTime;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Arr;

trait Attachable
{
    /**
     * @var Closure
     */
    private $canAttachCallback;

    /**
     * @var Closure
     */
    private $canDetachCallback;

    public function canAttach(Closure $callback)
    {
        $this->canAttachCallback = $callback;

        return $this;
    }

    /**
     * @param Closure $callback
     * @return $this
     */
    public function canDetach(Closure $callback)
    {
        $this->canDetachCallback = $callback;

        return $this;
    }

    public function authorizedToAttach(RestifyRequest $request, Pivot $pivot): bool
    {
        return is_callable($this->canAttachCallback)
            ? call_user_func($this->canAttachCallback, $request, $pivot)
            : true;
    }

    public function authorizeToAttach(RestifyRequest $request)
    {
        collect(Arr::wrap($request->input($request->relatedRepository)))->each(function ($relatedRepositoryId) use ($request) {
            $pivot = $this->initializePivot(
                $request, $request->findModelOrFail()->{$request->viaRelationship ?? $request->relatedRepository}(), $relatedRepositoryId
            );

            if (! $this->authorizedToAttach($request, $pivot)) {
                throw new AuthorizationException();
            }
        });

        return $this;
    }

    public function authorizedToDetach(RestifyRequest $request, Pivot $pivot): bool
    {
        return is_callable($this->canDetachCallback)
            ? call_user_func($this->canDetachCallback, $request, $pivot)
            : true;
    }

    public function authorizeToDetach(RestifyRequest $request, Pivot $pivot)
    {
        if (! $this->authorizedToDetach($request, $pivot)) {
            throw new AuthorizationException();
        }

        return $this;
    }

    public function initializePivot(RestifyRequest $request, $relationship, $relatedKey)
    {
        $parentKey = $request->repositoryId;

        $parentKeyName = $relationship->getParentKeyName();
        $relatedKeyName = $relationship->getRelatedKeyName();

        if ($parentKeyName !== $request->model()->getKeyName()) {
            $parentKey = $request->findModelOrFail()->{$parentKeyName};
        }

        if ($relatedKeyName !== ($request->newRelatedRepository()::newModel())->getKeyName()) {
            $relatedKey = $request->findRelatedModelOrFail()->{$relatedKeyName};
        }

        ($pivot = $relationship->newPivot())->forceFill([
            $relationship->getForeignPivotKeyName() => $parentKey,
            $relationship->getRelatedPivotKeyName() => $relatedKey,
        ]);

        if ($relationship->withTimestamps) {
            $pivot->forceFill([
                $relationship->createdAt() => new DateTime,
                $relationship->updatedAt() => new DateTime,
            ]);
        }

        return $pivot;
    }
}