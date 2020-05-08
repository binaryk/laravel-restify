<?php

namespace Binaryk\LaravelRestify\Repositories;

use Binaryk\LaravelRestify\Contracts\RestifySearchable;
use Binaryk\LaravelRestify\Controllers\RestResponse;
use Binaryk\LaravelRestify\Exceptions\InstanceOfException;
use Binaryk\LaravelRestify\Exceptions\UnauthorizedException;
use Binaryk\LaravelRestify\Fields\Field;
use Binaryk\LaravelRestify\Fields\FieldCollection;
use Binaryk\LaravelRestify\Http\Requests\RepositoryDestroyRequest;
use Binaryk\LaravelRestify\Http\Requests\RepositoryStoreRequest;
use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Restify;
use Binaryk\LaravelRestify\Services\Search\RepositorySearchService;
use Binaryk\LaravelRestify\Traits\InteractWithSearch;
use Binaryk\LaravelRestify\Traits\PerformsQueries;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\ConditionallyLoadsAttributes;
use Illuminate\Http\Resources\DelegatesToResource;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonSerializable;

/**
 * This class serve as repository collection and repository single model
 * This allow you to use all of the Laravel default repositories features (as adding headers in the response, or customizing
 * response).
 * @author Eduard Lupacescu <eduard.lupacescu@binarcode.com>
 */
abstract class Repository implements RestifySearchable, JsonSerializable
{
    use InteractWithSearch,
        ValidatingTrait,
        PerformsQueries,
        ConditionallyLoadsAttributes,
        DelegatesToResource;

    /**
     * This is named `resource` because of the forwarding properties from DelegatesToResource trait.
     * This may be a single model or a illuminate collection, or even a paginator instance.
     *
     * @var Model|LengthAwarePaginator
     */
    public $resource;

    /**
     * The list of relations available for the details or index.
     *
     * e.g. ?with=users
     * @var array
     */
    public static $related;

    /**
     * The list of searchable fields.
     *
     * @var array
     */
    public static $search;

    /**
     * The list of matchable fields.
     *
     * @var array
     */
    public static $match;

    /**
     * The list of fields to be sortable.
     *
     * @var array
     */
    public static $sort;

    /**
     * Get the underlying model instance for the resource.
     *
     * @return \Illuminate\Database\Eloquent\Model|LengthAwarePaginator
     */
    public function model()
    {
        return $this->resource ?? static::newModel();
    }

    /**
     * Get the URI key for the resource.
     *
     * @return string
     */
    public static function uriKey()
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
     * Get a fresh instance of the model represented by the resource.
     *
     * @return mixed
     */
    public static function newModel(): Model
    {
        if (property_exists(static::class, 'model')) {
            $model = static::$model;
        } else {
            $model = NullModel::class;
        }

        return new $model;
    }

    public static function query(): Builder
    {
        return static::newModel()->query();
    }

    /**
     * Resolvable attributes before storing/updating.
     *
     * @param RestifyRequest $request
     * @return array
     */
    public function fields(RestifyRequest $request)
    {
        return [];
    }

    /**
     * @param RestifyRequest $request
     * @return FieldCollection
     */
    public function collectFields(RestifyRequest $request)
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

        $fields = FieldCollection::make(array_values($this->filter($this->{$method}($request))));

        if ($this instanceof Mergeable) {
            $fillable = collect($this->resource->getFillable())
                ->filter(fn ($attribute) => $fields->contains('attribute', $attribute) === false)
                ->map(fn ($attribute) => Field::new($attribute));

            $fields = $fields->merge($fillable);
        }

        return $fields;
    }

    private function indexFields(RestifyRequest $request): Collection
    {
        return $this->collectFields($request)
            ->filter(fn(Field $field) => !$field->isHiddenOnIndex($request, $this))
            ->values();
    }

    private function showFields(RestifyRequest $request): Collection
    {
        return $this->collectFields($request)
            ->filter(fn(Field $field) => !$field->isHiddenOnShow($request, $this))
            ->values();
    }

    private function updateFields(RestifyRequest $request)
    {
        return $this->collectFields($request)
            ->forUpdate($request, $this)
            ->authorizedUpdate($request);
    }

    private function storeFields(RestifyRequest $request)
    {
        return $this->collectFields($request)
            ->forStore($request, $this)
            ->authorizedStore($request);
    }

    /**
     * @param $resource
     * @return Repository
     */
    public function withResource($resource)
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
    public static function resolveWith($model)
    {
        /** * @var Repository $self */
        $self = resolve(static::class);

        return $self->withResource($model);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Forward calls to the model (getKey() for example).
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
     * @param Router $router
     * @param array $attributes
     * @param bool $wrap Choose the routes defined in the @routes method, should be wrapped in a group with attributes by default.
     * If true then all routes will be grouped in a configuration attributes passed by restify, otherwise
     * you should take care of that, by adding $router->group($attributes) in the @routes method
     */
    public static function routes(Router $router, $attributes, $wrap = false)
    {
        $router->group($attributes, function ($router) {
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
        $fields = $this->showFields($request)
            ->filter(fn(Field $field) => $field->authorize($request))
            ->each(fn(Field $field) => $field->resolveForShow($this))
            ->map(fn(Field $field) => $field->serializeToValue($request))
            ->mapWithKeys(fn($value) => $value)
            ->all();

        if ($this instanceof Mergeable) {
            // Hiden and authorized index fields
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

                    if (!$field->authorize($request)) {
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
     * @param RestifyRequest $request
     * @return array
     */
    public function resolveIndexAttributes($request)
    {
        // Resolve the show method, and attach the value to the array
        $fields = $this->indexFields($request)
            ->filter(fn(Field $field) => $field->authorize($request))
            ->each(fn(Field $field) => $field->resolveForIndex($this))
            ->map(fn(Field $field) => $field->serializeToValue($request))
            ->mapWithKeys(fn($value) => $value)
            ->all();

        if ($this instanceof Mergeable) {
            // Hiden and authorized index fields
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

                    if (!$field->authorize($request)) {
                        return false;
                    }

                    return true;
                })->all();
        }

        return $fields;
    }

    /**
     * @param $request
     * @return array
     */
    public function resolveDetailsMeta($request)
    {
        return [
            'authorizedToShow' => $this->authorizedToShow($request),
            'authorizedToStore' => $this->authorizedToStore($request),
            'authorizedToUpdate' => $this->authorizedToUpdate($request),
            'authorizedToDelete' => $this->authorizedToDelete($request),
        ];
    }

    /**
     * Return a list with relationship for the current model.
     *
     * @param $request
     * @return array
     */
    public function resolveRelationships($request): array
    {
        if (is_null($request->get('related'))) {
            return [];
        }

        $withs = [];

        with(explode(',', $request->get('related')), function ($relations) use ($request, &$withs) {
            foreach ($relations as $relation) {
                if (in_array($relation, static::getRelated())) {
                    // @todo check if the resource has the relation
                    /** * @var AbstractPaginator $paginator */
                    $paginator = $this->resource->{$relation}()->paginate($request->get('relatablePerPage') ?? (static::$defaultRelatablePerPage ?? RestifySearchable::DEFAULT_RELATABLE_PER_PAGE));

                    $withs[$relation] = $paginator->getCollection()->map(fn(Model $item) => [
                        'attributes' => $item->toArray(),
                    ]);
                }
            }
        });

        return $withs;
    }

    /**
     * @param $request
     * @return array
     */
    public function resolveIndexMeta($request)
    {
        return $this->resolveDetailsMeta($request);
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
        throw_if($this->model() instanceof NullModel, InstanceOfException::because(__('Model is not defined in the repository.')));

        /** *
         * Apply all of the query: search, match, sort, related.
         * @var AbstractPaginator $paginator
         */
        $paginator = RepositorySearchService::instance()->search($request, $this)->tap(function ($query) use ($request) {
            // Call the local definition of the query
            static::indexQuery($request, $query);
        })->paginate($request->perPage ?? (static::$defaultPerPage ?? RestifySearchable::DEFAULT_PER_PAGE));

        $items = $paginator->getCollection()->map(function ($value) {
            return static::resolveWith($value);
        })->filter(function (self $repository) use ($request) {
            return $repository->authorizedToShow($request);
        })->values()->map(fn(self $repository) => $repository->serializeForIndex($request));

        return $this->response([
            'meta' => RepositoryCollection::meta($paginator->toArray()),
            'links' => RepositoryCollection::paginationLinks($paginator->toArray()),
            'data' => $items,
        ]);
    }

    public function show(RestifyRequest $request, $repositoryId)
    {
        return $this->response()->data($this->serializeForShow($request));
    }

    public function store(RestifyRequest $request)
    {
        DB::transaction(function () use ($request) {
            static::fillFields(
                $request, $this->resource, $this->storeFields($request)
            );

            $this->storeFields($request)->each(fn(Field $field) => $field->invokeAfter($this->resource, $request));

            $this->resource->save();
        });

        static::stored($this->resource, $request);

        return $this->response()
            ->created()
            ->model($this->resource)
            ->header('Location', static::uriTo($this->resource));
    }

    public function update(RestifyRequest $request, $repositoryId)
    {
        $this->resource = DB::transaction(function () use ($request) {
            $fields = $this->updateFields($request);

            static::fillFields($request, $this->resource, $fields);

            $this->resource->save();

            return $this->resource;
        });

        return $this->response()
            ->data($this->serializeForShow($request))
            ->success();
    }

    public function destroy(RestifyRequest $request, $repositoryId)
    {
        $this->allowToDestroy($request);

        $status = static::destroyPlain($repositoryId);

        static::deleted($status);

        return $this->response()->deleted();
    }

    public function allowToUpdate(RestifyRequest $request, $payload = null): self
    {
        $this->authorizeToUpdate($request);

        $validator = static::validatorForUpdate($request, $this, $payload);

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

    public function allowToDestroy(RestifyRequest $request)
    {
        $this->authorizeToDelete($request);

        return $this;
    }

    public function allowToShow($request): self
    {
        $this->authorizeToShow($request);

        return $this;
    }

    public static function destroyPlain($key)
    {
        /** * @var RepositoryDestroyRequest $request */
        $request = resolve(RepositoryDestroyRequest::class);

        $repository = $request->newRepositoryWith($request->findModelQuery($key, static::uriKey())->firstOrFail(), static::uriKey());

        $repository->allowToDestroy($request);

        return DB::transaction(function () use ($repository) {
            return $repository->resource->delete();
        });
    }

    public static function stored($repository, $request)
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
            'id' => $this->when($this->resource->id, fn() => $this->getShowId($request)),
            'type' => $this->when($type = $this->getType($request), $type),
            'attributes' => $request->isShowRequest() ? $this->resolveShowAttributes($request) : $this->resolveIndexAttributes($request),
            'relationships' => $this->when(value($related = $this->resolveRelationships($request)), $related),
            'meta' => $this->when(value($meta = $request->isShowRequest() ? $this->resolveDetailsMeta($request) : $this->resolveIndexMeta($request)), $meta),
        ]);
    }

    public function serializeForIndex(RestifyRequest $request): array
    {
        return $this->filter([
            'id' => $this->when($id = $this->getShowId($request), $id),
            'type' => $this->when($type = $this->getType($request), $type),
            'attributes' => $this->when((bool)$attrs = $this->resolveIndexAttributes($request), $attrs),
            'relationships' => $this->when(value($related = $this->resolveRelationships($request)), $related),
            'meta' => $this->when(value($meta = $this->resolveIndexMeta($request)), $meta),
        ]);
    }

    protected function getType(RestifyRequest $request): ?string
    {
        return $this->model()->getTable();
    }

    protected function getShowId(RestifyRequest $request): ?string
    {
        return $this->resource->getKey();
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
     * @param RestifyRequest $request
     * @param Model $model
     * @param Collection $fields
     * @return Collection
     */
    protected static function fillFields(RestifyRequest $request, Model $model, Collection $fields)
    {
        return $fields->map(function (Field $field) use ($request, $model) {
            return $field->fillAttribute($request, $model);
        });
    }



    public static function uriTo(Model $model)
    {
        return Restify::path() . '/' . static::uriKey() . '/' . $model->getKey();
    }
}
