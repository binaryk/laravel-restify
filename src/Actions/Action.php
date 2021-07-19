<?php

namespace Binaryk\LaravelRestify\Actions;

use Binaryk\LaravelRestify\Http\Requests\ActionRequest;
use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Models\ActionLog;
use Binaryk\LaravelRestify\Restify;
use Binaryk\LaravelRestify\Traits\AuthorizedToSee;
use Binaryk\LaravelRestify\Traits\Make;
use Binaryk\LaravelRestify\Traits\ProxiesCanSeeToGate;
use Binaryk\LaravelRestify\Traits\Visibility;
use Binaryk\LaravelRestify\Transaction;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonSerializable;

abstract class Action implements JsonSerializable
{
    use AuthorizedToSee;
    use ProxiesCanSeeToGate;
    use Make;
    use Visibility;

    /**
     * Number of models into a chunk when action for 'all'.
     *
     * @var int
     */
    public static int $chunkCount = 200;

    /**
     * Indicated if this action don't require any models.
     *
     * @var bool
     */
    public bool $standalone = false;

    /**
     * Default uri key for the action.
     * @var string
     */
    public static $uriKey;

    public static function indexQuery(RestifyRequest $request, $query)
    {
        //
    }

    /**
     * The callback used to authorize running the action.
     *
     * @var Closure|null
     */
    public ?Closure $runCallback;

    public function name()
    {
        return Restify::humanize($this);
    }

    /**
     * Get the URI key for the action.
     *
     * @return string
     */
    public function uriKey()
    {
        return static::$uriKey ?? Str::slug($this->name(), '-', null);
    }

    /**
     * Determine if the action is executable for the given request.
     *
     * @param Request $request
     * @param Model $model
     * @return bool
     */
    public function authorizedToRun(Request $request, $model)
    {
        return $this->runCallback ? call_user_func($this->runCallback, $request, $model) : true;
    }

    /**
     * Set the callback to be run to authorize running the action.
     *
     * @param Closure $callback
     * @return $this
     */
    public function canRun(Closure $callback)
    {
        $this->runCallback = $callback;

        return $this;
    }

    /**
     * Get the payload available on the action.
     *
     * @return array
     */
    public function payload(): array
    {
        return [];
    }

    /**
     * Make current action being standalone. No model query will be performed.
     *
     * @param bool $standalone
     * @return self
     */
    public function standalone(bool $standalone = true): self
    {
        $this->standalone = $standalone;

        return $this;
    }

    /**
     * Check if the action is standalone.
     *
     * @return bool
     */
    public function isStandalone(): bool
    {
        return $this->standalone;
    }

//    abstract public function handle(ActionRequest $request, Collection $models): JsonResponse;

    public function handleRequest(ActionRequest $request)
    {
        if (! method_exists($this, 'handle')) {
            throw new Exception('Missing handle method from the action.');
        }

        if ($this->isStandalone()) {
            return Transaction::run(fn () => $this->handle($request));
        }

        $response = null;

        if (! $request->isForRepositoryRequest()) {
            $request->collectRepositories($this, static::$chunkCount, function ($models) use ($request, &$response) {
                Transaction::run(function () use ($models, $request, &$response) {
                    $response = $this->handle($request, $models);

                    $models->each(fn (Model $model) => ActionLog::forRepositoryAction($this, $model, $request->user())->save());
                });
            });
        } else {
            Transaction::run(function () use ($request, &$response) {
                $response = $this->handle(
                    $request,
                    $model = tap($request->modelQuery(), function ($query) use ($request) {
                        static::indexQuery($request, $query);
                    })->firstOrFail()
                );

                Restify::actionLog()::forRepositoryAction(
                    $this,
                    $model,
                    $request->user()
                )->save();
            });
        }

        return $response;
    }

    public function jsonSerialize()
    {
        return array_merge([
            'name' => $this->name(),
            'destructive' => $this instanceof DestructiveAction,
            'uriKey' => $this->uriKey(),
            'payload' => $this->payload(),
        ]);
    }
}
