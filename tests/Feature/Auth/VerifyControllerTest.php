<?php

namespace Binaryk\LaravelRestify\Tests\Feature\Auth;

use Binaryk\LaravelRestify\Tests\Fixtures\User\User;
use Binaryk\LaravelRestify\Tests\IntegrationTest;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

class VerifyControllerTest extends IntegrationTest
{
    public function test_user_can_verify_account()
    {
        $this->withoutExceptionHandling();

        Route::restifyAuth();

        Event::fake([
            Verified::class,
        ]);

        $userRegistered = User::create([
            'name' => 'Vasile',
            'email' => 'vasile.papuc@gmail.com',
            'password' => 'secret!',
            'email_verified_at' => null,
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Vasile',
            'email' => 'vasile.papuc@gmail.com',
            'email_verified_at' => null,
        ]);

        $this->assertNull($userRegistered->email_verified_at);

        $verifiedUser = $this->postJson(route('restify.verify', [
            $userRegistered->id,
            sha1($userRegistered->email),
        ]))->assertOk()->json();

        $this->assertNotNull($verifiedUser['email_verified_at']);

        Event::assertDispatched(Verified::class, function ($e) use ($verifiedUser) {
            $this->assertEquals($e->user->email, $verifiedUser['email']);

            return $e->user instanceof \Binaryk\LaravelRestify\Tests\Fixtures\User\User;
        });
    }

    public function test_user_cant_verify_account()
    {
        Route::restifyAuth();

        Event::fake([
            Verified::class,
        ]);

        $userRegistered = User::create([
            'name' => 'Vasile',
            'email' => 'vasile.papuc@gmail.com',
            'password' => 'secret!',
            'email_verified_at' => null,
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Vasile',
            'email' => 'vasile.papuc@gmail.com',
            'email_verified_at' => null,
        ]);

        $this->assertNull($userRegistered->email_verified_at);

        $this->postJson(route('restify.verify', [
            $userRegistered->id,
            sha1('exemple@exemple.com'),
        ]))->assertForbidden();

        Event::assertNotDispatched(Verified::class);
    }
}
