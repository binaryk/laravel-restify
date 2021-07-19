<?php

namespace Binaryk\LaravelRestify\Http\Middleware;

use Binaryk\LaravelRestify\Bootstrap\Boot;
use Closure;
use Illuminate\Http\Request;

class RestifyInjector
{
    public function handle(Request $request, Closure $next)
    {
        app(Boot::class)->boot();

        return $next($request);
    }
}
