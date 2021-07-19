<?php

namespace Binaryk\LaravelRestify\Tests\Feature\Auth;

use Binaryk\LaravelRestify\Mail\ForgotPasswordMail;
use Binaryk\LaravelRestify\Tests\Fixtures\User\User;
use Binaryk\LaravelRestify\Tests\IntegrationTest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

class ForgotPasswordControllerTest extends IntegrationTest
{
    public function test_user_can_use_forgot_password_method()
    {
        Mail::fake();
        Route::restifyAuth();

        $user = User::create([
            'name' => 'Vasile',
            'email' => 'vasile.papuc@binarode.com',
            'password' => Hash::make('secret!'),
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'vasile.papuc@binarode.com',
        ]);

        $this->postJson(route('restify.forgotPassword', [
            'email' => $user->email,
        ]))->assertOk();

        Mail::assertSent(ForgotPasswordMail::class);
    }

    public function test_user_cant_get_reset_password_mail()
    {
        Mail::fake();
        Route::restifyAuth();

        $this->assertDatabaseMissing('users', [
            'email' => 'vasile@binarcode.com',
        ]);

        $this->postJson(route('restify.forgotPassword', [
            'email' => 'vasile@binarcode.com',
        ]))->assertNotFound();

        Mail::assertNotSent(ForgotPasswordMail::class);
    }
}
