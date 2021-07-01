<?php


namespace Binaryk\LaravelRestify\Tests\Unit;

use Binaryk\LaravelRestify\Commands\PublishAuthControllerCommand;
use Binaryk\LaravelRestify\Tests\IntegrationTest;

class PublishAuthControllersCommandTest extends IntegrationTest
{
    public function test_publish_controller_from_package_to_laravel_project()
    {
        $this->withoutExceptionHandling();

       $this->artisan(PublishAuthControllerCommand::class, ['name' => 'restify:publish-controllers'])
           ->expectsOutput('Restify Controllers & Emails published successfully')
           ->assertExitCode(1);
    }
}
