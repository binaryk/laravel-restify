<?php

namespace Binaryk\LaravelRestify\Bootstrap;

use Binaryk\LaravelRestify\Events\RestifyBeforeEach;
use Illuminate\Http\Request;

class Boot
{
    public function __construct(
        private Request $request,
        private RoutesBoot $routesBoot,
    ) {
    }

    public function boot(): void
    {
        RestifyBeforeEach::dispatch($this->request);

        if (isRestify($this->request)) {
            $this->routesBoot->boot();
        }
    }
}
