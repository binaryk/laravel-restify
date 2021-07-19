<?php

namespace Binaryk\LaravelRestify\Repositories;

use Binaryk\LaravelRestify\Actions\Action;
use Binaryk\LaravelRestify\Contracts\RestifySearchable;
use Binaryk\LaravelRestify\Eager\Related;
use Binaryk\LaravelRestify\Eager\RelatedCollection;
use Binaryk\LaravelRestify\Exceptions\InstanceOfException;
use Binaryk\LaravelRestify\Fields\BelongsToMany;
use Binaryk\LaravelRestify\Fields\EagerField;
use Binaryk\LaravelRestify\Fields\Field;
use Binaryk\LaravelRestify\Fields\FieldCollection;
use Binaryk\LaravelRestify\Http\Controllers\RestResponse;
use Binaryk\LaravelRestify\Http\Requests\RepositoryStoreBulkRequest;
use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Models\Concerns\HasActionLogs;
use Binaryk\LaravelRestify\Models\CreationAware;
use Binaryk\LaravelRestify\Repositories\Concerns\InteractsWithAttachers;
use Binaryk\LaravelRestify\Repositories\Concerns\InteractsWithModel;
use Binaryk\LaravelRestify\Repositories\Concerns\Mockable;
use Binaryk\LaravelRestify\Restify;
use Binaryk\LaravelRestify\Services\Search\RepositorySearchService;
use Binaryk\LaravelRestify\Traits\InteractWithSearch;
use Binaryk\LaravelRestify\Traits\PerformsQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\ConditionallyLoadsAttributes;
use Illuminate\Http\Resources\DelegatesToResource;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonSerializable;

abstract class Repository implements RestifySearchable, JsonSerializable
{
    use InteractWithSearch;
    use ValidatingTrait;
    use PerformsQueries;
    use ConditionallyLoadsAttributes;
    use DelegatesToResource;
    use ResolvesActions;
    use RepositoryEvents;
    use WithRoutePrefix;
    use InteractWithFields;
    use InteractsWithModel;
    use InteractsWithAttachers;
    use Mockable;

    /**
     * This is named `resource` because of the forwarding properties from DelegatesToResource trait.
     * This may be a single model or a illuminate collection, or even a paginator instance.
     *
     * @var Model
     */
    public Model $resource;

    /**
     * The list of relations available for the show or index.
     *
     * e.g. ?related=users
     * @var array
     */
    public static array $related;

    /**
     * The relationships that should be eager loaded when performing an index query.
     *
     * @var array
     */
    public static array $with = [];

    /**
     * The list of searchable fields.
     *
     * @var array
     */
    public static array $search;

    /**
     * The list of matchable fields.
     *
     * @var array
     */
    public static array $match;

    /**
     * The list of fields to be sortable.
     *
     * @var array
     */
    public static array $sort;

    /**
     * Attribute that should be used for displaying single model.
     *
     * @var string
     */
    public static string $title = 'id';

    /**
     * Attribute that should be used for displaying the `id` in the json:format.
     *
     * @var string
     */
    public static string $id = 'id';

    /**
     * Indicates if the repository should be globally searchable.
     *
     * @var bool
     */
    public static bool $globallySearchable = true;

    /**
     * The number of results to display in the global search.
     *
     * @var int
     */
    public static int $globalSearchResults = 5;

    /**
     * The list of middlewares for the current repository.
     *
     * @var array
     */
    public static array $middleware = [];

    /**
     * The list of attach callable's.
     *
     * @var array
     */
    public static array $attachers = [];

    /**
     * The list of detach callable's.
     *
     * @var array
     */
    public static array $detachers = [];

    /**
     * Indicates if the repository is serializing for a eager relationship.
     *
     * @var bool
     */
    public bool $eagerState = false;

    /**
     * Extra fields attached to the repository. Useful when display pivot fields.
     *
     * @var array
     */
    public array $extraFields = [];

    /**
     * A collection of pivots for the nested relationships.
     *
     * @var PivotsCollection
     */
    private PivotsCollection $pivots;

    public function __construct()
    {
        $this->bootIfNotBooted();
    }

    /**
     * Get the URI key for the repository.
     *
     * @return string
     */
    public static function uriKey(): string
    {
        if (property_exists(static::class, 'uriKey') && is_string(static::$uriKey)) {
            return static::$uriKey;
        }

        $kebabWithoutRepository = Str::kebab(Str::replaceLast('Repository', '', class_basename(get_called_class())));

        /**
         * e.g. UserRepository => users
         * e.g. LaravelEntityRepository => laravel-entities.
         */
        return Str::plural($kebabWithoutRepository);
    }

    /**
     * Get the label for the resource.
     *
     * @return string
     */
    public static function label()
    {
        if (property_exists(static::class, 'label') && is_string(static::$label)) {
            return static::$label;
        }

        $title = Str::title(Str::replaceLast('Repository', '', class_basename(get_called_class())));

        return Str::plural($title);
    }

    /**
     * Get the value that should be displayed to represent the repository.
     *
     * @return string
     */
    public function title(): string
    {
        return $this->{static::$title};
    }

    /**
     * Get the search result subtitle for the repository.
     *
     * @return string|null
     */
    public function subtitle(): ?string
    {
        return null;
    }

    public function filters(RestifyRequest $request): array
    {
        return [];
    }

    public function collectFields(RestifyRequest $request): FieldCollection
    {
        $method = 'fields';

        if ($request->isIndexRequest() && method_exists($this, 'fieldsForIndex')) {
            $method = 'fieldsForIndex';
        }

        if ($request->isShowRequest() && method_exists($this, 'fieldsForShow')) {
            $method = 'fieldsForShow';
        }

        if ($request->isUpdateRequest() && method_exists($this, 'fieldsForUpdate')) {
            $method = 'fieldsForUpdate';
        }

        if ($request->isStoreRequest() && method_exists($this, 'fieldsForStore')) {
            $method = 'fieldsForStore';
        }

        if ($request->isStoreBulkRequest() && method_exists($this, 'fieldsForStoreBulk')) {
            $method = 'fieldsForStoreBulk';
        }

        if ($request->isUpdateBulkRequest() && method_exists($this, 'fieldsForUpdateBulk')) {
            $method = 'fieldsForUpdateBulk';
        }

        return FieldCollection::make(
            array_values($this->filter($this->{$method}($request)))
        )->merge(
            $this->extraFields($request)
        )->setRepository($this);
    }

    public function extraFields(RestifyRequest $request): array
    {
        return $this->extraFields;
    }

    public function withExtraFields(array $fields): self
    {
        $this->extraFields = $fields;

        return $this;
    }

    public function withPivots(PivotsCollection $pivots): self
    {
        $this->pivots = $pivots;

        return $this;
    }

    public function getPivots(): ?PivotsCollection
    {
        return isset($this->pivots)
            ? $this->pivots
            : null;
    }

    public function withResource($resource): self
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Resolve repository with given model.
     *
     * @param $model
     * @return Repository
     */
    public static function resolveWith($model): Repository
    {
        if (static::isMock()) {
            return static::getMock()->withResource($model);
        }

        return resolve(static::class)->withResource($model);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return app(static::class)->$method(...$parameters);
    }

    /**
     * Forward calls to the model (getKey() for example).
     *
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->model(), $method, $parameters);
    }

    /**
     * Defining custom routes.
     *
     * The prefix of this route is the uriKey (e.g. 'restify-api/orders'),
     * The namespace is Http/Controllers
     * Middlewares are the same from config('restify.middleware').
     *
     * However all options could be customized by passing an $options argument
     *
     * @param  Router  $router
     * @param  array  $attributes
     * @param  bool  $wrap  Choose the routes defined in the @routes method, should be wrapped in a group with attributes by default.
     * If true then all routes will be grouped in a configuration attributes passed by restify, otherwise
     * you should take care of that, by adding $router->group($attributes) in the @routes method
     */
    public static function routes(Router $router, array $attributes, $wrap = true)
    {
        $router->group($attributes, function () {
            // override for custom routes
        });
    }

    /**
     * Return the attributes list.
     *
     * Resolve all model fields through showCallback methods and exclude from the final response if
     * that is required by method
     *
     * @param $request
     * @return array
     */
    public function resolveShowAttributes(RestifyRequest $request)
    {
        $fields = $this->collectFields($request)
            ->forShow($request, $this)
            ->filter(fn (Field $field) => $field->authorize($request))
            ->when(
                $this->isEagerState(),
                function ($items) {
                    return $items->filter(fn (Field $field) => ! $field instanceof EagerField);
                }
            )
            ->each(fn (Field $field) => $field->resolveForShow($this))
            ->map(fn (Field $field) => $field->serializeToValue($request))
            ->mapWithKeys(fn ($value) => $value)
            ->all();

        if ($this instanceof Mergeable) {
            // Hidden and authorized index fields
            $fields = $this->modelAttributes($request)
                ->filter(function ($value, $attribute) use ($request) {
                    /** * @var Field $field */
                    $field = $this->collectFields($request)->firstWhere('attribute', $attribute);

                    if (is_null($field)) {
                        return true;
                    }

                    if ($field->isHiddenOnShow($request, $this)) {
                        return false;
                    }

                    if (! $field->authorize($request)) {
                        return false;
                    }

                    return true;
                })->all();
        }

        return $fields;
    }

    /**
     * Return the attributes list.
     *
     * @param  RestifyRequest  $request
     * @return array
     */
    public function resolveIndexAttributes($request)
    {
        // Resolve the show method, and attach the value to the array
        $fields = $this
            ->collectFields($request)
            ->forIndex($request, $this)
            ->filter(fn (Field $field) => $field->authorize($request))
            ->when(
                $this->eagerState,
                function ($items) {
                    return $items->filter(fn (Field $field) => ! $field instanceof EagerField);
                }
            )
            ->each(fn (Field $field) => $field->resolveForIndex($this))
            ->map(fn (Field $field) => $field->serializeToValue($request))
            ->mapWithKeys(fn ($value) => $value)
            ->all();

        if ($this instanceof Mergeable) {
            // Hidden and authorized index fields
            $fields = $this->modelAttributes($request)
                ->filter(function ($value, $attribute) use ($request) {
                    /** * @var Field $field */
                    $field = $this->collectFields($request)->firstWhere('attribute', $attribute);

                    if (is_null($field)) {
                        return true;
                    }

                    if ($field->isHiddenOnIndex($request, $this)) {
                        return false;
                    }

                    if (! $field->authorize($request)) {
                        return false;
                    }

                    return true;
                })->all();
        }

        return $fields;
    }

    public function resolveShowMeta($request)
    {
        return [
            'authorizedToShow' => $this->authorizedToShow($request),
            'authorizedToStore' => $this->authorizedToStore($request),
            'authorizedToUpdate' => $this->authorizedToUpdate($request),
            'authorizedToDelete' => $this->authorizedToDelete($request),
        ];
    }

    public function resolveShowPivots(RestifyRequest $request): array
    {
        if (is_null($pivots = $this->getPivots())) {
            return [];
        }

        return $pivots
            ->filter(fn (Field $field) => $field->authorize($request))
            ->each(fn (Field $field) => $field->resolve($this))
            ->map(fn (Field $field) => $field->serializeToValue($request))
            ->mapWithKeys(fn ($value) => $value)
            ->all();
    }

    public function resolveIndexPivots(RestifyRequest $request): array
    {
        return $this->resolveShowPivots($request);
    }

    /**
     * Return a list with relationship for the current model.
     *
     * @param  RestifyRequest  $request
     * @return array
     */
    public function resolveRelationships($request): array
    {
        return static::collectRelated()
            ->authorized($request)
            ->inRequest($request)
            ->when($request->isShowRequest(), function (RelatedCollection $collection) use ($request) {
                return $collection->forShow($request, $this);
            })
            ->when($request->isIndexRequest(), function (RelatedCollection $collection) use ($request) {
                return $collection->forIndex($request, $this);
            })
            ->mapIntoRelated($request)
            ->map(function (Related $related) use ($request) {
                return $related->resolve($request, $this)->getValue();
            })->all();
    }

    /**
     * Returns the format of the metadata for individual item in the index response.
     *
     * @param $request
     * @return array
     */
    public function resolveIndexMeta($request)
    {
        return $this->resolveShowMeta($request);
    }

    /**
     * Return a list with relationship for the current model.
     *
     * @param $request
     * @return array
     */
    public function resolveIndexRelationships($request)
    {
        return $this->resolveRelationships($request);
    }

    public function index(RestifyRequest $request)
    {
        // Check if the user has the policy allowRestify

        // Check if the model was set under the repository
        throw_if(
            $this->model() instanceof NullModel,
            InstanceOfException::because(__('Model is not defined in the repository.'))
        );

        /** *
         * Apply all of the query: search, match, sort, related.
         * @var AbstractPaginator $paginator
         */
        $paginator = RepositorySearchService::instance()->search($request, $this)
            ->paginate($request->query('perPage') ?? static::$defaultPerPage);

        $items = $this->indexCollection($request, $paginator->getCollection())->map(function ($value) {
            return static::resolveWith($value);
        })->filter(function (self $repository) use ($request) {
            return $repository->authorizedToShow($request);
        })->values();

        return response()->json(
            $this->filter([
                'meta' => $this->when(
                    $meta = $this->resolveIndexMainMeta(
                        $request,
                        $models = $items->map(fn (self $repository) => $repository->resource),
                        RepositoryCollection::meta($paginator->toArray())
                    ),
                    $meta
                ),
                'links' => $this->when(
                    $links = $this->resolveIndexLinks(
                        $request,
                        $models,
                        array_merge(RepositoryCollection::paginationLinks($paginator->toArray()), [
                            'filters' => Restify::path(static::uriKey().'/filters'),
                        ])
                    ),
                    $links
                ),
                'data' => $items->map(fn (self $repository) => $repository->serializeForIndex($request)),
            ])
        );
    }

    public function indexCollection(RestifyRequest $request, Collection $items): Collection
    {
        return $items;
    }

    public function resolveIndexMainMeta(RestifyRequest $request, Collection $items, array $paginationMeta): ?array
    {
        return $paginationMeta;
    }

    public function resolveIndexLinks(RestifyRequest $request, Collection $items, array $links): ?array
    {
        return $links;
    }

    public function show(RestifyRequest $request, $repositoryId)
    {
        return $this->response()->data($this->serializeForShow($request));
    }

    public function store(RestifyRequest $request)
    {
        DB::transaction(function () use ($request) {
            static::fillFields(
                $request,
                $this->resource,
                $fields = $this->collectFields($request)
                    ->forStore($request, $this)
                    ->authorizedStore($request)
                    ->merge($this->collectFields($request)->forBelongsTo($request))
            );

            $dirty = $this->resource->getDirty();

            if ($request->isViaRepository()) {
                $this->resource = $request->viaQuery()->save($this->resource);
            } else {
                if ($this->resource instanceof CreationAware) {
                    $this->resource = $this->resource->createWithAttributes(
                        $this->resource->toArray()
                    );
                } else {
                    $this->resource->save();
                }
            }

            if (in_array(HasActionLogs::class, class_uses_recursive($this->resource))) {
                Restify::actionLog()
                    ->forRepositoryStored($this->resource, $request->user(), $dirty)
                    ->save();
            }

            $fields->each(fn (Field $field) => $field->invokeAfter($request, $this->resource));
        });

        static::stored($this->resource, $request);

        return $this->response()
            ->created()
            ->header('Location', static::uriTo($this->resource))
            ->data($this->serializeForShow(
                $request,
            ));
    }

    public function storeBulk(RepositoryStoreBulkRequest $request)
    {
        $entities = DB::transaction(function () use ($request) {
            return $request->collectInput()
                ->map(function (array $input, $row) use ($request) {
                    $this->resource = static::newModel();

                    static::fillBulkFields(
                        $request,
                        $this->resource,
                        $fields = $this->collectFields($request)
                            ->forStoreBulk($request, $this)
                            ->authorizedUpdateBulk($request),
                        $row
                    );

                    $this->resource->save();

                    $fields->each(fn (Field $field) => $field->invokeAfter($request, $this->resource));

                    return $this->resource;
                });
        });

        static::storedBulk($entities, $request);

        return $this->response()
            ->data($entities)
            ->success();
    }

    public function update(RestifyRequest $request, $repositoryId)
    {
        DB::transaction(function () use ($request) {
            $fields = $this->collectFields($request)
                ->forUpdate($request, $this)
                ->authorizedUpdate($request)
                ->merge($this->collectFields($request)->forBelongsTo($request));

            static::fillFields($request, $this->resource, $fields);

            if (in_array(HasActionLogs::class, class_uses_recursive($this->resource))) {
                Restify::actionLog()
                    ->forRepositoryUpdated($this->resource, $request->user())
                    ->save();
            }

            $this->resource->save();

            return $fields;
        })->each(
            fn (Field $field) => $field->invokeAfter($request, $this->resource)
        );

        return $this->response()
            ->data($this->serializeForShow($request))
            ->success();
    }

    public function patch(RestifyRequest $request, $repositoryId)
    {
        DB::transaction(function () use ($request) {
            $fields = $this->collectFields($request)
                ->intersectByKeys($request->json()->keys())
                ->forUpdate($request, $this)
                ->authorizedPatch($request)
                ->merge($this->collectFields($request)->forBelongsTo($request));

            static::fillFields($request, $this->resource, $fields);

            if (in_array(HasActionLogs::class, class_uses_recursive($this->resource))) {
                Restify::actionLog()
                    ->forRepositoryUpdated($this->resource, $request->user())
                    ->save();
            }

            $this->resource->save();

            return $fields;
        })->each(
            fn (Field $field) => $field->invokeAfter($request, $this->resource)
        );

        return $this->response()
            ->data($this->serializeForShow($request))
            ->success();
    }

    public function updateBulk(RestifyRequest $request, $repositoryId, int $row)
    {
        $fields = $this->collectFields($request)
            ->forUpdateBulk($request, $this)
            ->authorizedUpdateBulk($request);

        static::fillBulkFields($request, $this->resource, $fields, $row);

        $this->resource->save();

        static::updatedBulk($this->resource, $request);

        return response()->json();
    }

    public function attach(RestifyRequest $request, $repositoryId, Collection $pivots)
    {
        $eagerField = $this->authorizeBelongsToMany($request)->belongsToManyField($request);

        DB::transaction(function () use ($request, $pivots, $eagerField) {
            $fields = $eagerField->collectPivotFields()->filter(fn (
                $pivotField
            ) => $request->has($pivotField->attribute))->values();

            $pivots->map(function ($pivot) use ($request, $fields, $eagerField) {
                $eagerField->validate($request, $pivot);

                static::validatorForAttach($request)->validate();

                static::fillFields($request, $pivot, $fields);

                $eagerField->authorizeToAttach($request);

                return $pivot;
            })->each->save();
        });

        return $this->response()
            ->created()
            ->data($pivots);
    }

    public function detach(RestifyRequest $request, $repositoryId, Collection $pivots)
    {
        /** * @var BelongsToMany $eagerField */
        $eagerField = $request->repository()::collectRelated()
            ->forManyToManyRelations($request)
            ->firstWhere('attribute', $request->relatedRepository);

        $deleted = DB::transaction(function () use ($pivots, $eagerField, $request) {
            return $pivots
                ->map(fn ($pivot) => $eagerField->authorizeToDetach($request, $pivot) && $pivot->delete());
        });

        return $this->response()
            ->data($deleted)
            ->deleted();
    }

    public function destroy(RestifyRequest $request, $repositoryId)
    {
        $status = DB::transaction(function () use ($request) {
            if (in_array(HasActionLogs::class, class_uses_recursive($this->resource))) {
                Restify::actionLog()
                    ->forRepositoryDestroy($this->resource, $request->user())
                    ->save();
            }

            return $this->resource->delete();
        });

        static::deleted($status, $request);

        return $this->response()->deleted();
    }

    public function allowToUpdate(RestifyRequest $request, $payload = null): self
    {
        $this->authorizeToUpdate($request);

        $validator = static::validatorForUpdate($request, $this, $payload);

        $validator->validate();

        return $this;
    }

    public function allowToPatch(RestifyRequest $request, $payload = null): self
    {
        $this->authorizeToUpdate($request);

        $validator = static::validatorForPatch($request, $this, $payload);

        $validator->validate();

        return $this;
    }

    public function allowToAttach(RestifyRequest $request, Collection $attachers): self
    {
        $methodGuesser = 'attach'.Str::studly($request->relatedRepository);

        $attachers->each(fn ($model) => $this->authorizeToAttach($request, $methodGuesser, $model));

        return $this;
    }

    public function allowToDetach(RestifyRequest $request, Collection $attachers): self
    {
        $methodGuesser = 'detach'.Str::studly($request->relatedRepository);

        $attachers->each(fn ($model) => $this->authorizeToDetach($request, $methodGuesser, $model));

        return $this;
    }

    public function allowToUpdateBulk(RestifyRequest $request, $payload = null): self
    {
        $this->authorizeToUpdateBulk($request);

        $validator = static::validatorForUpdateBulk($request, $this, $payload);

        $validator->validate();

        return $this;
    }

    public function allowToStore(RestifyRequest $request, $payload = null): self
    {
        static::authorizeToStore($request);

        $validator = static::validatorForStoring($request, $payload);

        $validator->validate();

        return $this;
    }

    public function allowToBulkStore(RestifyRequest $request, $payload = null, $row = null): self
    {
        static::authorizeToStoreBulk($request);

        $validator = static::validatorForStoringBulk($request, $payload, $row);

        $validator->validate();

        return $this;
    }

    public function allowToDestroy(RestifyRequest $request)
    {
        $this->authorizeToDelete($request);

        return $this;
    }

    /**
     * @param $request
     * @return $this
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function allowToShow($request): self
    {
        $this->authorizeToShow($request);

        return $this;
    }

    public static function stored($repository, $request)
    {
        //
    }

    public static function storedBulk(Collection $repositories, $request)
    {
        //
    }

    public static function updatedBulk($model, $request)
    {
        //
    }

    public static function updated($model, $request)
    {
        //
    }

    public static function deleted($status, $request)
    {
        //
    }

    public function response($content = '', $status = 200, array $headers = []): RestResponse
    {
        return new RestResponse($content, $status, $headers);
    }

    public function serializeForShow(RestifyRequest $request): array
    {
        return $this->filter([
            'id' => $this->when(optional($this->resource)->id, fn () => $this->getId($request)),
            'type' => $this->when($type = $this->getType($request), $type),
            'attributes' => $request->isShowRequest() ? $this->resolveShowAttributes($request) : $this->resolveIndexAttributes($request),
            'relationships' => $this->when(value($related = $this->resolveRelationships($request)), $related),
            'meta' => $this->when(
                value($meta = $request->isShowRequest() ? $this->resolveShowMeta($request) : $this->resolveIndexMeta($request)),
                $meta
            ),
            'pivots' => $this->when(value($pivots = $this->resolveShowPivots($request)), $pivots),
        ]);
    }

    public function serializeForIndex(RestifyRequest $request): array
    {
        return $this->filter([
            'id' => $this->when($id = $this->getId($request), $id),
            'type' => $this->when($type = $this->getType($request), $type),
            'attributes' => $this->when((bool) $attrs = $this->resolveIndexAttributes($request), $attrs),
            'relationships' => $this->when(value($related = $this->resolveIndexRelationships($request)), $related),
            'meta' => $this->when(value($meta = $this->resolveIndexMeta($request)), $meta),
            'pivots' => $this->when(value($pivots = $this->resolveIndexPivots($request)), $pivots),
        ]);
    }

    protected function getType(RestifyRequest $request): ?string
    {
        return $this->model()->getTable();
    }

    protected function getId(RestifyRequest $request): ?string
    {
        if (! static::$id) {
            return null;
        }

        return collect($this->resource->getHidden())->contains(static::$id)
            ? null
            : $this->resource->getAttribute(static::$id);
    }

    public function jsonSerialize()
    {
        return $this->serializeForShow(app(RestifyRequest::class));
    }

    private function modelAttributes(Request $request = null): Collection
    {
        return collect(method_exists($this->resource, 'toArray') ? $this->resource->toArray() : []);
    }

    /**
     * Fill each field separately.
     *
     * @param  RestifyRequest  $request
     * @param  Model  $model
     * @param  Collection  $fields
     * @return Collection
     */
    protected static function fillFields(RestifyRequest $request, Model $model, Collection $fields)
    {
        return $fields->map(fn (Field $field) => $field->fillAttribute($request, $model));
    }

    protected static function fillBulkFields(
        RestifyRequest $request,
        Model $model,
        Collection $fields,
        int $bulkRow = null
    ) {
        return $fields->map(function (Field $field) use ($request, $model, $bulkRow) {
            return $field->fillAttribute($request, $model, $bulkRow);
        });
    }

    public static function uriTo(Model $model): string
    {
        return Str::replaceFirst('//', '/', Restify::path().'/'.static::uriKey().'/'.$model->getKey());
    }

    public static function collectMiddlewares(RestifyRequest $request): Collection
    {
        return collect(static::$middleware);
    }

    public static function getAttachers(): array
    {
        return static::$attachers;
    }

    public static function getDetachers(): array
    {
        return static::$detachers;
    }

    public function eagerState($state = true): Repository
    {
        $this->eagerState = $state;

        return $this;
    }

    public function isEagerState(): bool
    {
        return $this->eagerState === true;
    }

    public function restifyjsSerialize(RestifyRequest $request): array
    {
        return [
            'uriKey' => static::uriKey(),
            'related' => static::collectFilters('matches'),
            'sort' => static::collectFilters('sortables'),
            'match' => static::collectFilters('matches'),
            'searchables' => static::collectFilters('searchables'),
            'actions' => $this->resolveActions($request)->filter(fn (Action $action) => $action->isShownOnIndex(
                $request,
                $this
            ))->values(),
        ];
    }

    public static function to(string $path = null, array $query = []): string
    {
        $base = Restify::path().'/'.static::uriKey();

        $route = $path
            ? $base.'/'.$path
            : $base;

        return $route.'?'.http_build_query($query);
    }
}
