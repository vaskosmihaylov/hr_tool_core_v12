<?php


namespace viki\Service\Http\Middleware;

use App\Models\Resource;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class ApplicationPermission
{
    public function handle($request, Closure $next)
    {
        $url = $request->path();

        if (!Auth::check()) {
//            return route('login');
            return redirect('login');
        }
        if (Auth::user()->hasPermissionUrl($url)) {
            return $next($request);
        } else {
            return abort(404);
        }
    }
}
