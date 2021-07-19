# Authentication setup

Laravel Restify has support for authentication with [Laravel Sanctum](https://laravel.com/docs/sanctum#api-token-authentication).

You'll finally enjoy the auth setup (`register`, `login`, `forgot` and `reset password`).

## Prerequisites
- Migrate the `users`, `password_resets` table (they already exists into a fresh Laravel app).

- Migrate the `personal_access_tokens` table, provided by sanctum.

- Install laravel sanctum. See the docs [here](https://laravel.com/docs/sanctum#installation). You don't need to add `\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,` in your `'api'` middleware group. So you only need to run these 3 commands: 

```shell script
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

- Make sure your authenticatable entity (usually `App\Models\User`) implements: `Illuminate\Contracts\Auth\Authenticatable` (or simply extends the `Illuminate\Foundation\Auth\User` class as it does into a fresh laravel app.)

- Make sure the `App\Models\User` model implements the `Binaryk\LaravelRestify\Contracts\Sanctumable` contract.

- Add `\Laravel\Sanctum\HasApiTokens` trait to your `User` model.

## Define routes

Restify provides you a simple way to add all of your auth routes ready. Simply add in your `routes/api.php`:

```php
Route::restifyAuth();
```

And voila, now you have auth routes ready to be used.

These are default routes provided by restify: 

| Verb           | URI                                      | Action           | 
| :------------- |:-----------------------------------------| :----------------|
| POST           | `/api/register`                          | register         |
| POST           | `/api/login`                             | login            |
| POST           | `/api/restify/forgotPassword`            | forgot password  |
| POST           | `/api/restify/resetPassword`             | reset password   |
| POST           | `/api/restify/verify/{id}/{emailHash}`   | verify user      |

All of these routes are handle by default, so you can just use them. However, you can customize each of them by defining your own auth routes, and still benefit of the Restify AuthController by extending it. 

Let's take a closer look over each route in part.

## Publish controllers

After installation user can publish controllers for full control on it.

```shell script
php artisan restify:publish-controllers
```

The command above does:

-  Creates on path `app/Http/Controllers/Restify/Auth` controllers files
-  Creates mail required folder on path `app/Mail/Restify/Auth` with `ForgotPasswordMail.php` file
-  Creates specific blade file on `resources/views/Restify/Auth` with `reset-password.blade.php`
-  Register the route in `app/Providers/RestifyServiceProvider.php`

## Register users

- Define a register route to an action controller:

```php
Route::post('register', 'AuthController@register');
```

- Inject the AuthService into your controller and call the register method:

```php
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Binaryk\LaravelRestify\Services\AuthService;

class AuthController
{
    /**
     * @var AuthService
     */
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }
    /**
     * This will validate the input, 
     * will register the user and return it back to the api
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request)
    {
        $user = $this->authService->register($request->all());

        return Response::make(['data' => $user], 201);
    }
```

- Validating input

Restify will automatically validate the data send from the request to the `register` method. 

Used validation FormRequest is: `Binaryk\LaravelRestify\Http\Requests\RestifyRegisterRequest`

However if you want to validate the registration payload yourself you can disable the built in validation by nullifying `$registerFormRequest`:

```php
public function register(UserRegisterRequest $request)
{
    AuthService::$registerFormRequest = null;
    
    $user = $this->authService->register($request->only(array_keys($request->rules())));

    return Response::make(['data' => $user], 201);
}
```

Or you could simply set the `$registerFormRequest` with your custom FormRequest:

```php
public function register(Request $request)
{
    AuthService::$registerFormRequest = UserRegisterRequest::class;
    
    $user = $this->authService->register($request->all());

    return Response::make(['data' => $user], 201);
}
```

- Exceptions

If something went wrong inside the register method, the AuthService will thrown few suggestive exceptions you can handle in the controller:

 > `\Binaryk\LaravelRestify\Exceptions\AuthenticatableUserException` - Make sure your authenticatable entity (usually `User`) is implementing the `Illuminate\Contracts\Auth\Authenticatable` interface.
 
 > `\Binaryk\LaravelRestify\Exceptions\Eloquent\EntityNotFoundException` - Class (usually `App\User`) defined in the configuration `auth.providers.users.model` could not been instantiated (may be abstract or missing at all)
    
- After successfully registering user, an `Illuminate\Auth\Events\Registered` event will be dispatched.

## Verifying users (optional)

This is an optional feature, but sometimes we may want users to validation the registered email.

- Prerequisites

Make sure `User` model implementing the `Illuminate\Contracts\Auth\MustVerifyEmail` contract.

The `MustVerifyEmail` contract will wait for a `sendEmailVerificationNotification` method definition. 

This method could look like this:

```php
// app/User.php

public function sendEmailVerificationNotification()
{
    $this->notify(new \Binaryk\LaravelRestify\Notifications\VerifyEmail);
}
```

The `VerifyEmail` should send the notification email to the user. This email should include two required data:
> the sha1 hash of the user email 

> user id

so your frontend application could easily make a verify call to the API with this data. 

Example of notification:

```php
namespace Binaryk\LaravelRestify\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailLaravel;

class VerifyEmail extends VerifyEmailLaravel
{
    /**
     * Get the verification URL for the given notifiable.
     *
     * @param mixed $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        $withToken = str_replace(['{id}'], $notifiable->getKey(), config('restify.auth.user_verify_url'));
        $withEmail = str_replace(['{emailHash}'], sha1($notifiable->getEmailForVerification()), $withToken);

        return url($withEmail);
    }
}
```

As you may noticed it uses a route, let's scaffolding an verify route example as well:

```php
Route::get('api/verify/{id}/{hash}', 'AuthController@verify')
    ->name('register.verify')
    ->middleware([ 'throttle:6,1' ]);
```

So your frontend could call this route with the `id` and `hash` received into verify email.

- Next let's define the controller action:

```php
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Binaryk\LaravelRestify\Services\AuthService;

class AuthController
{
    /**
     * @var AuthService
     */
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * This will mark the email verified if the email sha1 hash and user id matches
     * 
     * @param Request $request
     * @return JsonResponse
     * @throws AuthorizationException - thrown if hash or id doesn't match
     * @throws \Binaryk\LaravelRestify\Exceptions\Eloquent\EntityNotFoundException - thrown if the user not found
     */
    public function verify(Request $request, $id, $hash = null)
    {
        $user = $this->authService->verify($request, $id, $hash);

        return Response::make(['data' => $user]);
    }
}
```

- After verifying with success an `Illuminate\Auth\Events\Verified` event is dispatched.

## Login users (issue token)
After having user registered and verified (if the case) the API should be able to issue personal authorization tokens.

- Define a login route to an action controller:

```php
Route::post('login', 'AuthController@login');
```

- Inject the AuthService into your controller and call the login method:

```php
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Binaryk\LaravelRestify\Services\AuthService;
use Binaryk\LaravelRestify\Exceptions\UnverifiedUser;
use Binaryk\LaravelRestify\Exceptions\CredentialsDoesntMatch;

class AuthController
{
    /**
     * @var AuthService
     */
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }
    /**
     * This will validate the input, 
     * will register the user and return it back to the api
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        try {
            $token = $this->authService->login($request);

            return Response::make(compact('token'));
        } catch (CredentialsDoesntMatch | UnverifiedUser) {
            return Response::make('Something went wrong.', 401);
        }
    }
}
```

The login method will thrown few exceptions:
> `Binaryk\LaravelRestify\Exceptions\CredentialsDoesntMatch` - when email or password doesn't match

> `Binaryk\LaravelRestify\Exceptions\UnverifiedUser` - when `User` model implements `Illuminate\Contracts\Auth\MustVerifyEmail` 
and he did not verified the email

- After login with success a personal token is issued and an `Binaryk\LaravelRestify\Events\UserLoggedIn` event is dispatched.

## Forgot password

Forgot password is the action performing by user in terms of recovering his lost password. Usually the API should send an email 
with a unique URL that allow users to reset password.

- Prerequisites:

If you want your users to be able to reset their passwords, make sure your `User` model implements the
`Illuminate\Contracts\Auth\CanResetPassword` contract.

This contract requires the `sendPasswordResetNotification` to be implemented. It could looks like this:

```php
/**
 * Send the password reset notification.
 *
 * @param string $token
 * @return void
 */
public function sendPasswordResetNotification($token)
{
        Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function ($notifiable, $token) {
            $withToken = str_replace(['{token}'], $token, config('restify.auth.password_reset_url'));
            $withEmail = str_replace(['{email}'], $notifiable->getEmailForPasswordReset(), $withToken);

            return url($withEmail);
        });
        
    $this->notify(new Illuminate\Auth\Notifications\ResetPassword($token));
}
```

As you can see, you can simply use the `ResetPassword` builtin notification.

The `getEmailForPasswordReset` method simply returns the user email:

```php
/**
 * @inheritDoc
 */
public function getEmailForPasswordReset()
{
    return $this->email;
}
```

- Define a forgot password route to an action controller:

```php
Route::post('api/forgotPassword', 'AuthController@forgotPassword');
```

- Inject the AuthService into your controller and call the `sendResetPasswordLinkEmail` method:

```php
use Binaryk\LaravelRestify\Exceptions\Eloquent\EntityNotFoundException;use Binaryk\LaravelRestify\Exceptions\PasswordResetException;use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Binaryk\LaravelRestify\Services\AuthService;use Illuminate\Validation\ValidationException;

class AuthController
{
    /**
     * @var AuthService
     */
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

     /**
      * This will validate the request input (email exists for example) and will send an reset passw
      * 
      * @param Request $request
      * @return JsonResponse
      */
     public function forgotPassword(Request $request)
     {
        try {
         $this->authService->forgotPassword($request);
 
         return Response::make('', 204);
        } catch (EntityNotFoundException $e) {
            // Defined in the configuration auth.providers.users.model could not been instantiated (may be abstract or missing at all)
        } catch (PasswordResetException $e) {
            // Something unexpected from the Broker class
        } catch (ValidationException $e) {
            // The email is not valid
        }
     }
```

## Reset password

Finally we have to reset the users passwords. This can easily be done by using Restify AuthService as well.

- Define a reset password route to an action controller:

```php
Route::post('api/resetPassword', 'AuthController@resetPassword')->name('password.reset');
```

- Inject the AuthService into your controller and call the resetPassword method:

```php
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Binaryk\LaravelRestify\Services\AuthService;

class AuthController
{
    /**
     * @var AuthService
     */
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

   /**
    * @param Request $request
    * @return JsonResponse
    * @throws \Binaryk\LaravelRestify\Exceptions\Eloquent\EntityNotFoundException
    * @throws \Illuminate\Contracts\Container\BindingResolutionException
    * @throws \Illuminate\Validation\ValidationException
    */
   public function resetPassword(Request $request)
   {
       try {
           $this->authService->resetPassword($request);

           return Response::make(__('Password reset'));
       } catch (PasswordResetException|PasswordResetInvalidTokenException $e) {
           return Response::make('Something went wrong', 401);
       }
   }

```

After successfully password reset an `Illuminate\Auth\Events\PasswordReset` event is dispatched.
