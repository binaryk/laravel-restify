<?php

namespace Binaryk\LaravelRestify\Fields;

use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Closure;
use Illuminate\Http\Request;

abstract class OrganicField extends BaseField
{
    public $canSeeCallback;

    public $canUpdateCallback;

    public $canPatchCallback;

    public $canUpdateBulkCallback;

    public $canStoreCallback;

    public $readonlyCallback;

    public $hiddenCallback;

    public array $rules = [];

    public array $storingRules = [];

    public array $storingBulkRules = [];

    public array $updateBulkRules = [];

    public array $updatingRules = [];

    public array $messages = [];

    public $showOnIndex = true;

    public $showOnShow = true;

    public function showOnShow($callback = true)
    {
        $this->showOnShow = $callback;

        return $this;
    }

    public function showOnIndex($callback = true)
    {
        $this->showOnIndex = $callback;

        return $this;
    }

    public function hideFromShow($callback = true)
    {
        $this->showOnShow = is_callable($callback) ? function () use ($callback) {
            return ! call_user_func_array($callback, func_get_args());
        }
        : ! $callback;

        return $this;
    }

    public function hideFromIndex($callback = true)
    {
        $this->showOnIndex = is_callable($callback) ? function () use ($callback) {
            return ! call_user_func_array($callback, func_get_args());
        }
        : ! $callback;

        return $this;
    }

    public function isShownOnShow(RestifyRequest $request, $repository): bool
    {
        if ($this->isHidden($request)) {
            return false;
        }

        if (is_callable($this->showOnShow)) {
            $this->showOnShow = call_user_func($this->showOnShow, $request, $repository);
        }

        return $this->showOnShow;
    }

    public function isHiddenOnShow(RestifyRequest $request, $repository): bool
    {
        return false === $this->isShownOnShow($request, $repository);
    }

    public function isShownOnIndex(RestifyRequest $request, $repository): bool
    {
        if ($this->isHidden($request)) {
            return false;
        }

        return false === $this->isHiddenOnIndex($request, $repository);
    }

    public function isHiddenOnIndex(RestifyRequest $request, $repository): bool
    {
        if (is_callable($this->showOnIndex)) {
            $this->showOnIndex = call_user_func($this->showOnIndex, $request, $repository);
        }

        return ! $this->showOnIndex;
    }

    public function authorize(Request $request)
    {
        return $this->authorizedToSee($request);
    }

    public function authorizedToSee(Request $request)
    {
        return $this->canSeeCallback ? call_user_func($this->canSeeCallback, $request) : true;
    }

    public function authorizedToUpdate(Request $request)
    {
        return $this->canUpdateCallback ? call_user_func($this->canUpdateCallback, $request) : true;
    }

    public function authorizedToPatch(Request $request)
    {
        return $this->canPatchCallback ? call_user_func($this->canPatchCallback, $request) : true;
    }

    public function authorizedToUpdateBulk(Request $request)
    {
        return $this->canUpdateBulkCallback ? call_user_func($this->canUpdateBulkCallback, $request) : true;
    }

    public function authorizedToStore(Request $request)
    {
        return $this->canStoreCallback ? call_user_func($this->canStoreCallback, $request) : true;
    }

    public function canSee(Closure $callback)
    {
        $this->canSeeCallback = $callback;

        return $this;
    }

    public function canUpdate(Closure $callback)
    {
        $this->canUpdateCallback = $callback;

        return $this;
    }

    public function canPatch(Closure $callback)
    {
        $this->canPatchCallback = $callback;

        return $this;
    }

    public function canUpdateBulk(Closure $callback)
    {
        $this->canUpdateBulkCallback = $callback;

        return $this;
    }

    public function canStore(Closure $callback)
    {
        $this->canStoreCallback = $callback;

        return $this;
    }

    public function readonly($callback = true)
    {
        $this->readonlyCallback = $callback;

        return $this;
    }

    public function isReadonly(RestifyRequest $request)
    {
        return with($this->readonlyCallback, function ($callback) use ($request) {
            if ($callback === true || (is_callable($callback) && call_user_func($callback, $request))) {
                return true;
            }

            return false;
        });
    }

    public function isShownOnUpdate(RestifyRequest $request, $repository): bool
    {
        return ! $this->isReadonly($request);
    }

    public function isShownOnUpdateBulk(RestifyRequest $request, $repository): bool
    {
        return ! $this->isReadonly($request);
    }

    public function isShownOnStore(RestifyRequest $request, $repository): bool
    {
        return ! $this->isReadonly($request);
    }

    public function isShownOnStoreBulk(RestifyRequest $request, $repository): bool
    {
        return ! $this->isReadonly($request);
    }

    public function isHidden(RestifyRequest $request)
    {
        return with($this->hiddenCallback, function ($callback) use ($request) {
            if ($callback === true || (is_callable($callback) && call_user_func($callback, $request))) {
                return true;
            }

            return false;
        });
    }
}
