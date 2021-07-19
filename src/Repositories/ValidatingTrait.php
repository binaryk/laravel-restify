<?php

namespace Binaryk\LaravelRestify\Repositories;

use Binaryk\LaravelRestify\Fields\BelongsToMany;
use Binaryk\LaravelRestify\Fields\Field;
use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

trait ValidatingTrait
{
    /**
     * @param  RestifyRequest  $request
     * @return Collection
     */
    abstract public function collectFields(RestifyRequest $request);

    /**
     * @return mixed
     */
    abstract public static function newModel();

    /**
     * @param  RestifyRequest  $request
     * @param  array  $plainPayload
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public static function validatorForStoring(RestifyRequest $request, array $plainPayload = null)
    {
        $on = static::resolveWith(static::newModel());

        $messages = $on->collectFields($request)->flatMap(function ($k) {
            $messages = [];
            foreach ($k->messages as $ruleFor => $message) {
                $messages[$k->attribute.'.'.$ruleFor] = $message;
            }

            return $messages;
        })->toArray();

        return Validator::make(
            $plainPayload ?? $request->all(),
            $on->getStoringRules($request),
            $messages
        )->after(function ($validator) use ($request) {
            static::afterValidation($request, $validator);
            static::afterStoringValidation($request, $validator);
        });
    }

    public static function validatorForStoringBulk(RestifyRequest $request, array $plainPayload = null)
    {
        $on = static::resolveWith(static::newModel());

        $messages = $on->collectFields($request)->flatMap(function ($k) {
            $messages = [];
            foreach ($k->messages as $ruleFor => $message) {
                $messages['*'.$k->attribute.'.'.$ruleFor] = $message;
            }

            return $messages;
        })->toArray();

        return Validator::make(
            $plainPayload ?? $request->all(),
            $on->getStoringBulkRules($request),
            $messages
        )->after(function ($validator) use ($request) {
            static::afterValidation($request, $validator);
            static::afterStoringBulkValidation($request, $validator);
        });
    }

    /**
     * Validate a resource update request.
     * @param  RestifyRequest  $request
     * @param  null  $resource
     */
    public static function validateForUpdate(RestifyRequest $request, $resource = null)
    {
        static::validatorForUpdate($request, $resource)->validate();
    }

    public static function validatorForUpdate(RestifyRequest $request, $resource = null, array $plainPayload = null)
    {
        /** * @var Repository $on */
        $on = $resource ?? static::resolveWith(static::newModel());

        $messages = $on->collectFields($request)->flatMap(function ($k) {
            $messages = [];
            foreach ($k->messages as $ruleFor => $message) {
                $messages[$k->attribute.'.'.$ruleFor] = $message;
            }

            return $messages;
        })->toArray();

        return Validator::make(
            $plainPayload ?? $request->all(),
            $on->getUpdatingRules($request),
            $messages
        )->after(function ($validator) use ($request) {
            static::afterValidation($request, $validator);
            static::afterUpdatingValidation($request, $validator);
        });
    }

    public static function validatorForPatch(RestifyRequest $request, $resource = null, array $plainPayload = null)
    {
        /** * @var Repository $on */
        $on = $resource ?? static::resolveWith(static::newModel());

        $messages = $on->collectFields($request)
            ->intersectByKeys($request->json()->keys())
            ->flatMap(function ($k) {
                $messages = [];
                foreach ($k->messages as $ruleFor => $message) {
                    $messages[$k->attribute.'.'.$ruleFor] = $message;
                }

                return $messages;
            })->toArray();

        return Validator::make(
            $plainPayload ?? $request->all(),
            collect($on->getUpdatingRules($request))->intersectByKeys($request->json())->all(),
            $messages
        )->after(function ($validator) use ($request) {
            static::afterValidation($request, $validator);
            static::afterUpdatingValidation($request, $validator);
        });
    }

    public static function validatorForAttach(RestifyRequest $request, $resource = null, array $plainPayload = null)
    {
        /** * @var Repository $on */
        $on = $resource ?? static::resolveWith(static::newModel());

        /** * @var BelongsToMany $field */
        $field = $on::collectRelated()
            ->forManyToManyRelations($request)
            ->firstWhere('attribute', $request->relatedRepository);

        $pivotFields = $field->collectPivotFields();

        $messages = $pivotFields->flatMap(fn (Field $field) => $field->serializeMessages())->all();

        $rules = $pivotFields->mapWithKeys(function (Field $k) {
            return [
                $k->attribute => $k->getStoringRules(),
            ];
        })->all();

        return Validator::make($plainPayload ?? $request->all(), $rules, $messages)->after(function ($validator) use (
            $request
        ) {
            static::afterValidation($request, $validator);
            static::afterUpdatingValidation($request, $validator);
        });
    }

    public static function validatorForUpdateBulk(RestifyRequest $request, $resource = null, array $plainPayload = null)
    {
        /** * @var Repository $on */
        $on = $resource ?? static::resolveWith(static::newModel());

        $messages = $on->collectFields($request)->flatMap(function ($k) {
            $messages = [];
            foreach ($k->messages as $ruleFor => $message) {
                $messages['*'.$k->attribute.'.'.$ruleFor] = $message;
            }

            return $messages;
        })->toArray();

        return Validator::make(
            $plainPayload ?? $request->all(),
            $on->getUpdatingBulkRules($request),
            $messages
        )->after(function ($validator) use ($request) {
            static::afterValidation($request, $validator);
            static::afterUpdatingBulkValidation($request, $validator);
        });
    }

    /**
     * Handle any post-validation processing.
     *
     * @param  RestifyRequest  $request
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    protected static function afterValidation(RestifyRequest $request, $validator)
    {
        //
    }

    protected static function afterStoringValidation(RestifyRequest $request, $validator)
    {
    }

    protected static function afterStoringBulkValidation(RestifyRequest $request, $validator)
    {
    }

    protected static function afterUpdatingValidation(RestifyRequest $request, $validator)
    {
    }

    protected static function afterUpdatingBulkValidation(RestifyRequest $request, $validator)
    {
    }

    /**
     * @param  RestifyRequest  $request
     * @return array
     */
    public function getStoringRules(RestifyRequest $request)
    {
        return $this->collectFields($request)->mapWithKeys(function (Field $k) {
            return [
                $k->attribute => $k->getStoringRules(),
            ];
        })->toArray();
    }

    public function getStoringBulkRules(RestifyRequest $request)
    {
        return $this->collectFields($request)->mapWithKeys(function (Field $k) {
            return [
                "*.{$k->attribute}" => $k->getStoringBulkRules(),
            ];
        })->toArray();
    }

    public function getUpdatingRules(RestifyRequest $request)
    {
        return $this->collectFields($request)->mapWithKeys(function (Field $k) {
            return [
                $k->attribute => $k->getUpdatingRules(),
            ];
        })->toArray();
    }

    public function getUpdatingBulkRules(RestifyRequest $request)
    {
        return $this->collectFields($request)->mapWithKeys(function (Field $k) {
            return [
                "*.{$k->attribute}" => $k->getUpdatingBulkRules(),
            ];
        })->toArray();
    }
}
