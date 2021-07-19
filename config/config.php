<?php

use Binaryk\LaravelRestify\Http\Middleware\AuthorizeRestify;
use Binaryk\LaravelRestify\Http\Middleware\DispatchRestifyStartingEvent;
use Binaryk\LaravelRestify\Http\Middleware\EnsureJsonApiHeaderMiddleware;
use Binaryk\LaravelRestify\Repositories\ActionLogRepository;

return [
    'auth' => [
        /*
        |--------------------------------------------------------------------------
        | Table containing authenticatable resource
        |--------------------------------------------------------------------------
        |
        | This configuration contain the name of the table used for the authentication.
        |
        */

        'table' => 'users',

        /*
        |--------------------------------------------------------------------------
        |
        |--------------------------------------------------------------------------
        |
        | Next you may configure the package you're using for the personal tokens generation,
        | this will be used for the verification of the authenticatable model and provide the
        | authorizable functionality
        |
        | Supported: "sanctum"
        */

        'provider' => 'sanctum',

        /*
        |--------------------------------------------------------------------------
        | Auth frontend app url
        |--------------------------------------------------------------------------
        |
        |URL used for reset password URL generating.
        |
        |
        */

        'frontend_app_url' => env('FRONTEND_APP_URL', env('APP_URL')),

        'password_reset_url' => env('FRONTEND_APP_URL').'/password/reset?token={token}&email={email}',

        'user_verify_url' => env('FRONTEND_APP_URL').'/verify/{id}/{emailHash}',

        'user_model' => \Illuminate\Foundation\Auth\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | RestifyJS
    |--------------------------------------------------------------------------
    |
    | This configuration is used for supporting the RestifyJS
    |
    */
    'restifyjs' => [
        /*
        | Token to authorize the setup endpoint.
        */
        'token' => env('RESTIFYJS_TOKEN', 'testing'),

        /*
        | The API base url.
        */
        'api_url' => env('API_URL', env('APP_URL')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Restify Base Route
    |--------------------------------------------------------------------------
    |
    | This configuration is used as a prefix path where Restify will be accessible from.
    | Feel free to change this path to anything you like.
    |
    */

    'base' => '/api/restify',

    /*
    |--------------------------------------------------------------------------
    | Restify Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to every Restify route, giving you the
    | chance to add your own middleware to this stack or override any of
    | the existing middleware. Or, you can just stick with this stack.
    |
    */

    'middleware' => [
        'api',
        EnsureJsonApiHeaderMiddleware::class,
        DispatchRestifyStartingEvent::class,
        AuthorizeRestify::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Used to format data.
    |--------------------------------------------------------------------------
    |
    */
    'casts' => [
        /*
        |--------------------------------------------------------------------------
        | Casting the related entities format.
        |--------------------------------------------------------------------------
        |
        */
        'related' => \Binaryk\LaravelRestify\Repositories\Casts\RelatedCast::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Restify Logs
    |--------------------------------------------------------------------------
    */
    'logs' => [
        /*
        | Repository used to list logs.
        */
        'repository' => ActionLogRepository::class,
    ],
];
