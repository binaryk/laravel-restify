<?php

namespace Binaryk\LaravelRestify\Fields;

use Binaryk\LaravelRestify\Contracts\Deletable as DeletableContract;
use Binaryk\LaravelRestify\Contracts\FileStorable as StorableContract;
use Binaryk\LaravelRestify\Fields\Concerns\AcceptsTypes;
use Binaryk\LaravelRestify\Fields\Concerns\Deletable;
use Binaryk\LaravelRestify\Fields\Concerns\FileStorable;
use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Repositories\Storable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class File extends Field implements StorableContract, DeletableContract
{
    use FileStorable, AcceptsTypes, Deletable;

    /**
     * The callback that should be used to determine the file's storage name.
     *
     * @var callable|null
     */
    public $storeAs;

    /**
     * The column where the file's original name should be stored.
     *
     * @var string
     */
    public $originalNameColumn;

    /**
     * The column where the file's size should be stored.
     *
     * @var string
     */
    public $sizeColumn;

    /**
     * The callback that should be executed to store the file.
     *
     * @var callable|Storable
     */
    public $storageCallback;

    public function __construct($attribute, callable $resolveCallback = null)
    {
        parent::__construct($attribute, $resolveCallback);

        $this->prepareStorageCallback();

        $this->delete(function () {
            if ($this->value) {
                Storage::disk($this->getStorageDisk())->delete($this->value);

                return $this->columnsThatShouldBeDeleted();
            }
        });
    }

    /**
     * Specify the callback or the name that should be used to determine the file's storage name.
     *
     * @param callable|string $storeAsCallback
     * @return $this
     */
    public function storeAs($storeAs): self
    {
        $this->storeAs = $storeAs;

        return $this;
    }

    /**
     * Prepare the storage callback.
     *
     * @param callable|null $storageCallback
     * @return void
     */
    protected function prepareStorageCallback(callable $storageCallback = null): void
    {
        $this->storageCallback = $storageCallback ?? function ($request, $model) {
            return $this->mergeExtraStorageColumns($request, [
                $this->attribute => $this->storeFile($request, $this->attribute),
            ]);
        };
    }

    /**
     * Specify the column where the file's original name should be stored.
     *
     * @param string $column
     * @return $this
     */
    public function storeOriginalName($column)
    {
        $this->originalNameColumn = $column;

        return $this;
    }

    /**
     * Specify the column where the file size should be stored.
     *
     * @param string $column
     * @return $this
     */
    public function storeSize($column)
    {
        $this->sizeColumn = $column;

        return $this;
    }

    protected function storeFile(Request $request, string $requestAttribute)
    {
        if (! $this->storeAs) {
            return $request->file($requestAttribute)->store($this->getStorageDir(), $this->getStorageDisk());
        }

        return $request->file($requestAttribute)->storeAs(
            $this->getStorageDir(),
            is_callable($this->storeAs) ? call_user_func($this->storeAs, $request) : $this->storeAs,
            $this->getStorageDisk()
        );
    }

    /**
     * Specify the callback that should be used to store the file.
     *
     * @param callable|Storable $storageCallback
     * @return $this
     */
    public function store($storageCallback): self
    {
        $this->storageCallback = is_subclass_of($storageCallback, Storable::class)
            ? [app($storageCallback), 'handle']
            : $storageCallback;

        return $this;
    }

    /**
     * Merge the specified extra file information columns into the storable attributes.
     *
     * @param \Illuminate\Http\Request $request
     * @param array $attributes
     * @return array
     */
    protected function mergeExtraStorageColumns($request, array $attributes): array
    {
        $file = $request->file($this->attribute);

        if ($this->originalNameColumn) {
            $attributes[$this->originalNameColumn] = $file->getClientOriginalName();
        }

        if ($this->sizeColumn) {
            $attributes[$this->sizeColumn] = $file->getSize();
        }

        return $attributes;
    }

    /**
     * Get an array of the columns that should be deleted and their values.
     *
     * @return array
     */
    protected function columnsThatShouldBeDeleted(): array
    {
        $attributes = [$this->attribute => null];

        if ($this->originalNameColumn) {
            $attributes[$this->originalNameColumn] = null;
        }

        if ($this->sizeColumn) {
            $attributes[$this->sizeColumn] = null;
        }

        return $attributes;
    }

    public function fillAttribute(RestifyRequest $request, $model, int $bulkRow = null)
    {
        if (is_null($file = $request->file($this->attribute)) || ! $file->isValid()) {
            return $this;
        }

        $result = call_user_func(
            $this->storageCallback,
            $request,
            $model,
            $this->attribute,
            $this->disk,
            $this->storagePath
        );

        if ($result === true) {
            return $this;
        }

        if ($result instanceof Closure) {
            return $result;
        }

        if (! is_array($result)) {
            return $model->{$attribute} = $result;
        }

        foreach ($result as $key => $value) {
            if ($model->isFillable($key)) {
                $model->{$key} = $value;
            }
        }

        if ($this->isPrunable()) {
            return function () use ($model, $request) {
                call_user_func(
                    $this->deleteCallback,
                    $request,
                    $model,
                    $this->getStorageDisk(),
                    $this->getStoragePath()
                );
            };
        }

        return $this;
    }

    /**
     * Get the full path that the field is stored at on disk.
     *
     * @return string|null
     */
    public function getStoragePath()
    {
        return $this->value;
    }
}
