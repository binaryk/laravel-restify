<?php

namespace Binaryk\LaravelRestify\Tests\Feature\Auth;

use Binaryk\LaravelRestify\Tests\IntegrationTest;
use Illuminate\Support\Facades\Route;

class RegisterControllerTest extends IntegrationTest
{
    public function test_user_can_successfully_register()
    {
        Route::restifyAuth();

        $this
            ->postJson(route('restify.register', [
                'name' => 'Vasile',
                'email' => 'test@binarcode.com',
                'password' => 'secret!',
                'password_confirmation' => 'secret!',
            ]))->assertok();

        $this->assertDatabaseHas('users', [
            'name' => 'Vasile',
            'email' => 'test@binarcode.com',
        ]);
    }
}
