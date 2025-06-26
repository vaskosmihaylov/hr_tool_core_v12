<?php

namespace App\Http\Middleware;

use App\Models\Resource;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplicationPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $url = $request->path();

        if (!Auth::check()) {
            return redirect('login');
        }

        if (Auth::user()->hasPermissionUrl($url)) {
            return $next($request);
        } else {
            return abort(404);
        }
    }
}
