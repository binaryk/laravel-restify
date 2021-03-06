# Actions 

[[toc]]

## Motivation

Restify allow you to define extra actions for your repositories. Let's say you have a list of posts, and you have to publish them. Usually for this kind of operations, you have to define a custom route like: 


```php
// PostRepository

public static function routes(Router $router, $attributes, $wrap = true)
{
    $router->post('/publish', [static::class, 'publishMultiple']);
}

public function publishMultiple(RestifyRequest $request)
{
  ...
}
```

There is nothing bad with this approach, but when project growth, you will notice that the routes / repository can easily become a mess. 

More than that, you have to guess a route name or handler, should it be a controller, a callback or a method right in the repository class? Well, for this kind of operations, actions is what we need.

# Defining Actions

The action could be generated by using the command: 

```bash
artisan restify:action PublishPostsAction
```

This will generate for the handler class:

```php
namespace App\Restify\Actions;

use Binaryk\LaravelRestify\Actions\Action;
use Binaryk\LaravelRestify\Http\Requests\ActionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class PublishPostAction extends Action
{
    public function handle(ActionRequest $request, Collection $models): JsonResponse
    {
        return $this->response()->respond();
    }
}
```

The `$models` represents a collection with all of the models for this query.


## Available actions

The frontend which consume your API could check available actions by using exposed endpoint: 

```http request
GET: api/restify-api/posts/actions
```

This will answer with a json like:

```json
[
"data": [
  {
    "name":  "Publish Posts Action",
    "destructive": false,
    "uriKey":  "publish-posts-action",
    "payload": []
  }
]
```

`name` - humanized name of the action

`destructive` - you may extend the `Binaryk\LaravelRestify\Actions\DestructiveAction` to indicate to the frontend than this action is destructive (could be used for deletions)

`uriKey` - is the key of the action, will be used to perform the action

`payload` - a key / value object indicating required payload

# Registering Actions

Once you have defined the action, you can register it for many resources. 


```php
public function actions(RestifyRequest $request)
{
    return [
        PublishPostAction::new(),
    ];
}
```

You can pass anything to the action constructor: 

```php
public function actions(RestifyRequest $request)
{
    return [
        PublishPostAction::new("Publish articles.", app(Validator::class)),
    ];
}
```

:::warning Repository model
You may consider that you have access to the `$this->resource` in the `actions` method (since it's not static).
However, in this method the `resource` is not available, since the request is for a new model.
:::

### Unauthorized

Actions could be authorized:

```php
public function actions(RestifyRequest $request)
{
    return [
        PublishPostAction::new()->canSee(function (Request $request) {
            return $request->user()->can('pubishAnyPost', Post::class),
        }),
    ];
}
```

### Authorizing Actions Per-Model

As you saw, we don't have access to the repository model in the `actions` method. However, we do have access to models in the handle method. You're free to use Laravel Policies there.


# Use actions

The usage of an action, means the `handle` method implementation. The first argument is the `RestifyRequest`, and the second one is a Collection of models, matching the `repositories` payload.


```http request
POST: api/restify-api/posts/actions?action=publish-posts-action
```

Payload:

```json
"repositories": [1, 2]
```

```php
public function handle(ActionRequest $request, Collection $models): JsonResponse
{
     // $models contains 2 posts (under ids 1 and 2)
    $models->each->publish();

    return $this->response()->respond();
}
```


## Filters

You can apply any filter or eager loadings as for an usual request: 

```http request
POST: api/restify-api/posts/actions?action=publish-posts-action&id=1&filters=
```

This will apply the match for the `id = 1` and `filtetr` along with the match for the `repositories` payload you're sending.

## All

Sometimes you may need to apply an action for all models. For this you can send: 

```http request
repositories: 'all'
```

## Chunk

Under the hood Restify will take by 200 chunks entries from the database and the handle method for these in a DB transaction. You are free to modify this default number of chunks: 

```php
public static int $chunkCount = 150;
```

## Action visibility

Usually you need an action only for a single model. Then you can use: 

```php
public function actions(RestifyRequest $request)
{
    return [
        PublishPostAction::new()->onlyOnShow(),
    ];
}
```


And available actions only for a specific repository id could be listed like:

```http request
GET: api/restify-api/posts/1/actions
```

Having this in place, you now have access to the current repository in the `actions` method: 

```php
public function actions(RestifyRequest $request)
{
    return [
        PublishPostAction::new()->onlyOnShow()->canSee(function(ActionRequest $request) {
            return $request->user()->ownsPost($request->findModelOrFail());
        })
    ];
}
```

Performing this action, you can only for a single repository: 

```http request
POST: api/restify-api/posts/1/actions?action=publish-posts-action
```

And you don't have to pass the `repositories` array in that case, since it's present in the query.

Because it will be right in your handle method: 

```php
public function handle(ActionRequest $request, Post $post): JsonResponse
{
    //
}
```

## Standalone actions

Sometimes you don't need to have an action with models. Let's say for example the authenticated user wants to disable his account. For this we have `standalone` actions:


```php
// UserRepository

    public function actions(RestifyRequest $request)
    {
        return [
            DisableProfileAction::new()->standalone(),
        ];
    }
```

Just mark it as standalone with `->standalone` or override the property directly into the action: 

```php
class DisableProfileAction extends Action
{
    public $standalone = true;

    //...
}
```


## URI Key

Usually the URL for the action is make based on the action name. You can use your own URI key if you want: 

```php
class DisableProfileAction extends Action
{
    public static $uriKey = 'disable_profile';

    //...
}
```
