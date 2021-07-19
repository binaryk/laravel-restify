<?php

namespace Binaryk\LaravelRestify\Filters;

use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Repositories\Repository;
use Binaryk\LaravelRestify\Restify;
use Binaryk\LaravelRestify\Traits\Make;
use Binaryk\LaravelRestify\Traits\Metable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonSerializable;

abstract class Filter implements JsonSerializable
{
    use Make;
    use HasMode;
    use Metable;

    public string $type = 'value';

    public ?string $title = null;

    public string $description = '';

    public ?string $column = null;

    public ?Closure $canSeeCallback = null;

    public static $uriKey;

    public ?string $relatedRepositoryKey = null;

    public ?string $relatedRepositoryTitle = null;

    public bool $advanced = false;

    public Repository $repository;

    public function __construct()
    {
        if ($this instanceof AdvancedFilter) {
            $this->setAdvanced();
        }

        if ($this instanceof SearchableFilter) {
            $this->type = $this->type ?? SearchableFilter::TYPE;
        }

        if ($this instanceof SortableFilter) {
            $this->type = $this->type ?? SortableFilter::TYPE;
        }

        if ($this instanceof MatchFilter) {
            $this->type = $this->type ?? MatchFilter::TYPE;
        }

        $this->booted();
    }

    protected function booted()
    {
        //
    }

    abstract public function filter(RestifyRequest $request, Builder | Relation $query, $value);

    public function canSee(Closure $callback)
    {
        $this->canSeeCallback = $callback;

        return $this;
    }

    public function authorizedToSee(RestifyRequest $request): bool
    {
        return $this->canSeeCallback ? call_user_func($this->canSeeCallback, $request) : true;
    }

    public function key(): string
    {
        return static::class;
    }

    protected function getType(): string
    {
        return $this->type;
    }

    protected function title(): string
    {
        if ($this->title) {
            return $this->title;
        }

        if ($column = $this->column()) {
            return Str::title(Str::snake(Str::studly($column), ' '));
        }

        return $this->title ?? Str::title(Str::snake(class_basename(static::class), ' '));
    }

    protected function description(): string
    {
        return $this->description;
    }

    /**
     * @return string|null
     * @deprecated use `column()` instead
     */
    public function getColumn(): ?string
    {
        return $this->column;
    }

    public function column(): ?string
    {
        return $this->getColumn();
    }

    public function getQueryKey(): ?string
    {
        return Str::after($this->getColumn(), '.');
    }

    public function setColumn(string $column): self
    {
        $this->column = $column;

        return $this;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function invalidPayloadValue(Request $request, $value)
    {
        if (is_array($value)) {
            return count($value) < 1;
        } elseif (is_string($value)) {
            return trim($value) === '';
        }

        return is_null($value);
    }

    public function getRelatedRepositoryKey(): ?string
    {
        return $this->relatedRepositoryKey;
    }

    public function setRelatedRepositoryKey(string $repositoryKey): self
    {
        $this->relatedRepositoryKey = $repositoryKey;

        return $this;
    }

    public function setRelatedRepositoryTitle(string $title): self
    {
        $this->relatedRepositoryTitle = $title;

        return $this;
    }

    public function setRepository(Repository $repository): self
    {
        $this->repository = $repository;

        return $this;
    }

    public function setAdvanced(bool $advanced = true): self
    {
        $this->advanced = true;

        return $this;
    }

    public function getRelatedRepository(): ?array
    {
        return ($key = $this->getRelatedRepositoryKey())
            ? with(Restify::repositoryForKey($key), function ($repository = null) {
                if (is_subclass_of($repository, Repository::class)) {
                    return [
                        'key' => $repository::uriKey(),
                        'url' => Restify::path($repository::uriKey()),
                        'display_key' => $this->relatedRepositoryTitle ?? $repository::$title,
                        'label' => $repository::label(),
                    ];
                }
            })
            : null;
    }

    /**
     * Get the URI key for the filter.
     *
     * @return string
     */
    public static function uriKey(): string
    {
        if (property_exists(static::class, 'uriKey') && is_string(static::$uriKey)) {
            return static::$uriKey;
        }

        $kebabWithoutFilter = Str::kebab(Str::replaceLast('Filter', '', class_basename(get_called_class())));

        return Str::plural($kebabWithoutFilter);
    }

    public function isAdvanced(): bool
    {
        return $this instanceof AdvancedFilter;
    }

    public function jsonSerialize()
    {
        $serialized = with([
            'type' => $this->getType(),
            'advanced' => $this->advanced,
            'title' => $this->title(),
            'description' => $this->description(),
            'column' => $this->column(),
            'key' => static::uriKey(),
        ], function (array $initial) {
            return $this->relatedRepositoryKey ? array_merge($initial, [
                'repository' => $this->getRelatedRepository(),
            ]) : $initial;
        });


        if ($this->isAdvanced()) {
            $serialized = array_merge($serialized, [
                'rules' => $this->rules(app(Request::class)),
                'options' => method_exists($this, 'options')
                    ? collect($this->options(app(Request::class)))->map(function ($key, $value) {
                        return is_array($value) ? ($value + ['property' => $key]) : ['label' => $key, 'property' => $value];
                    })->values()->all()
                    : [],
            ]);
        }

        return array_merge($serialized, $this->meta());
    }

    public function dd(): self
    {
        dd($this);

        return $this;
    }
}
