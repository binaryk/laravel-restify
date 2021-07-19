<?php

namespace Binaryk\LaravelRestify\Http\Controllers\Auth;

use Binaryk\LaravelRestify\Tests\Fixtures\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|confirmed',
        ]);

        /** * @var User $user */

        $user = config('config.auth.user_model')::query()->where($request->only('email'))->firstOrFail();

        if (! Password::getRepository()->exists($user, $request->input('token'))) {
            abort(400, 'Provided invalid token.');
        }

        $user->password = Hash::make($request->input('password'));
        $user->save();

        Password::deleteToken($user);

        return data("Password has been successfully changed");
    }
}
