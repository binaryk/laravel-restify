<?php

namespace Binaryk\LaravelRestify\Events;

class AddedRepositories
{
    public function __construct(
        public array $repositories
    ) {
    }
}
