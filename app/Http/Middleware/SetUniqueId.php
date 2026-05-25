<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUniqueId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract uniqueid from route parameter and set it as unique_id for other middlewares
        if ($request->route('uniqueid')) {
            $uniqueid = $request->route('uniqueid');
            $request->merge(['unique_id' => $uniqueid]);
            $request->request->add(['unique_id' => $uniqueid]);
        }

        return $next($request);
    }
}
