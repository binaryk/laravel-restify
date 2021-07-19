<?php

namespace Binaryk\LaravelRestify\Tests\Feature;

use Binaryk\LaravelRestify\Actions\Action;
use Binaryk\LaravelRestify\Models\ActionLog;
use Binaryk\LaravelRestify\Tests\Fixtures\User\User;
use Binaryk\LaravelRestify\Tests\IntegrationTest;

class ActionLogTest extends IntegrationTest
{
    public function test_can_create_log_for_repository_storing(): void
    {
        $this->authenticate();

        $log = ActionLog::forRepositoryStored(
            User::factory()->create(),
            $this->authenticatedAs
        );

        $log->save();

        $this->assertInstanceOf(ActionLog::class, $log);

        $this->assertDatabaseHas('action_logs', [
            'name' => 'Stored',
            'actionable_type' => User::class,
            'actionable_id' => 1,
        ]);

        $this->assertDatabaseCount('action_logs', 1);
    }

    public function test_can_create_log_for_repository_updating()
    {
        $this->authenticate();

        $user = User::factory()->create([
            'email' => 'initial@mail',
        ]);

        $user->email = 'foo@bar.com';

        $log = ActionLog::forRepositoryUpdated(
            $user,
            $this->authenticatedAs
        );

        $log->save();

        $this->assertInstanceOf(ActionLog::class, $log);

        $this->assertDatabaseHas('action_logs', [
            'name' => ActionLog::ACTION_UPDATED,
            'actionable_type' => User::class,
            'actionable_id' => 1,
        ]);

        $this->assertDatabaseCount('action_logs', 1);

        $this->assertSame([
            'email' => 'foo@bar.com',
        ], $log->changes);

        $this->assertSame([
            'email' => 'initial@mail',
        ], $log->original);
    }

    public function test_can_create_log_for_repository_deleting()
    {
        $this->authenticate();

        $user = User::factory()->create();

        $log = ActionLog::forRepositoryDestroy(
            $user,
            $this->authenticatedAs
        );

        $log->save();

        $this->assertInstanceOf(ActionLog::class, $log);

        $this->assertDatabaseHas('action_logs', [
            'name' => ActionLog::ACTION_DELETED,
            'actionable_type' => User::class,
            'actionable_id' => 1,
        ]);

        $this->assertDatabaseCount('action_logs', 1);
    }

    public function test_can_create_log_for_repository_custom_action()
    {
        $this->authenticate();

        $user = User::factory()->create();

        $action = new class extends Action {
            public static $uriKey = 'test action';
        };

        $log = ActionLog::forRepositoryAction(
            $action,
            $user,
            $this->authenticatedAs
        );

        $log->save();

        $this->assertInstanceOf(ActionLog::class, $log);

        $this->assertDatabaseHas('action_logs', [
            'name' => 'test action',
            'actionable_type' => User::class,
            'actionable_id' => 1,
        ]);

        $this->assertDatabaseCount('action_logs', 1);
    }
}
