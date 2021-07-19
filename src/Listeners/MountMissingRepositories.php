<?php

namespace Binaryk\LaravelRestify\Listeners;

use Binaryk\LaravelRestify\Bootstrap\BootRepository;
use Binaryk\LaravelRestify\Events\AddedRepositories;

class MountMissingRepositories
{
    public function handle(AddedRepositories $addedRepositories): void
    {
        collect($addedRepositories->repositories)->each(function (string $repository) {
            (new BootRepository($repository))->boot();
        });
    }
}
