<?php

namespace Binaryk\LaravelRestify\Events;

use Binaryk\LaravelRestify\Filters\AdvancedFiltersCollection;
use Binaryk\LaravelRestify\Repositories\Repository;

class AdvancedFiltersApplied
{
    public function __construct(
        public Repository $repository,
        public AdvancedFiltersCollection $advancedFiltersCollection,
        public ?string $rawFilters = null,
    ) {
    }
}
