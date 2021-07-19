# Upgrading

Because there are many breaking changes an upgrade is not that easy. There are many edge cases this guide does not cover. We accept PRs to improve this guide.

## From 4.10.x to 5.x

### Repository changes:
- `filters` - explicit `array` returned type
- `getSearchableFields()` - deprecated, to use `searchables`
- `getMatchByFields()` - deprecated, to use `matches`
- `getOrderByFields()` - deprecated, to use `sorts`
- `availableFilters` now returns an instance of `FiltersCollection`, so if you overwrite this, make sure to adapt.
- `uriTo` - explicit `string` returned type
- The support for relatable via query params was dropped because of security reasons. Now we only maintain the relatable via `BelongsToMany` or `HasMany` fields. ie: `/posts?parentRepository=users&parentRepositoryId=1` should be now: `/users/1/posts` and define the `'posts' => HasMany::new('')...` into your `UserRepository` 
- Repository `index`, `show`, `store`, `update`, `destroy` should specify the `JsonResponse` return.
- `getRelated` method was dropped - use `related` instead
- `getMatchByFields` method was dropped - use `matches` instead
- `getOrderByFields` method was dropped - use `sorts` instead
- `getSearchableFields` method was dropped - use `searchables` instead
- `getWiths` method was dropped - use `withs` instead
### Filters

- `uriKey()` - `string` returned type
- `BooleanFilter` - changed namespace to `Binaryk\LaravelRestify\Filters`
- `SelectFilter` - changed namespace to `Binaryk\LaravelRestify\Filters\SelectFilter` and must implement the `options` method and return an array. The key should be the value the frontend can send and value would be the label frontend could use to display the select. ie: ['is_active' => 'Is Active']
- There is no more `class` property for the advanced filters, the frontend should only send they `key` of the filter.
- The `filters` method from the repository should return a list of `AdvancedFilers`.
  
- The `resolve` method for the advanced filters now requires a `AdvancedFilterPayloadDto` instance.
- The `value` argument for the third parameter for `AdvancedFilters` will be resolved from the `AdvancedFilterPayloadDto@value` method
- Each advanced filter must implement the `rules` method which returns the validation payload for the filter.

### Profile
- POST `/profile/avatar` was deleted.

### RestController

- RestController namespace was changed to `Binaryk\LaravelRestify\Http\Controllers` so you should refactor all of yours classes where you have used it (tip: you can use `data()` helper to wrap any response into json with `data` key)
- `Action` base class doesn't extend anymore the `RestController`.
- `RestifyHandler` was removed.

### Fields

- For all related fields (BelongsTo, HasMany etc.) was dropped the argument one, so instead of `BelongsTo::make('user', 'user', UserRepository::class)` you have to use: `BelongsTo::make('user', UserRepository::class)`

### Others

- `RestifyServiceProviderRegistered` event was removed


## From 4.7.0 to 4.8.0 

- Copy the `database/migrations/create_action_logs_table.php` migration to yours local migrations and run `php artisan migrate` to ensure you can benefit from the `action logs`.

## From v3 to v4

- Dropped support for laravel passport
- Now you have to explicitly define the `allowRestify` method in the model policy, by default Restify don't allow you to use repositories.
- `viewAny` policy isn't used anymore, you can delete it.
- The default exception handler is the Laravel one, see `restify.php -> handler`
- `fillCallback` signature has changed
- By default it will do not allow you to attach `belongsToMany` and `morphToMany` relationships. You will have to add `BelongsToMany` or `MorphToMany` field into your repository
- All of the `Repository` getter methods should declare the returned type, for instance the `fieldsForIndex` method should say that it returns an `:array` 
- Attach endpoint:
```php
"api/restify/users/{$user->id}/attach/roles", [
    'roles' => [$role->id],
]
```
now requires to have a `Binaryk\LaravelRestify\Fields\BelongsToMany` or `Binaryk\LaravelRestify\Fields\MorphToMany` field to be defined in the repository.

- Field method `append` renamed to `value`.

- The relations from the Repository `$related`, which are resolved via a `Illuminate\Database\Eloquent\Relations\Relation` or `Illuminate\Database\Eloquent\Builder` will do not have anymore the `attributes` property in the relations. To support this format, you can configure a custom Cast on `restify.php`, see the `Binaryk\LaravelRestify\Tests\Fixtures\Post\RelatedCastWithAttributes::class`, it returns the old 3.x format.
