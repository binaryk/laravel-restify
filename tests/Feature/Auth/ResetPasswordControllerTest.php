<?php

namespace Binaryk\LaravelRestify\Tests\Feature\Auth;

use Binaryk\LaravelRestify\Tests\Fixtures\User\User;
use Binaryk\LaravelRestify\Tests\IntegrationTest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;

class ResetPasswordControllerTest extends IntegrationTest
{
    public function test_auth_user_can_reset_password()
    {
        Route::restifyAuth();

        $user = User::create([
            'name' => 'Vasile',
            'email' => 'vasile.papuc@binarcode.com',
            'password' => Hash::make('secret!'),
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'vasile.papuc@binarcode.com',
        ]);

        $token = Password::createToken($user);

        $this->actingAs($user);

        $response = $this->postJson(route('restify.resetPassword', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'secret!1',
            'password_confirmation' => 'secret!1',
        ]))->assertOk()->json('data');

        $this->assertTrue(Hash::check('secret!1', $user->fresh()->password));
        $this->assertSame($response, 'Password has been successfully changed');
    }
}
