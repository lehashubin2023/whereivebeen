<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyAdmins
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isAdmin()) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()->route('admin.issue-reports.index');
        }

        return $next($request);
    }
}
