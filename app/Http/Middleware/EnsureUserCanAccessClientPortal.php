<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessClientPortal
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Admins can always access the client portal
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // Users linked to a client account can access the portal
        if ($user->client_id !== null) {
            return $next($request);
        }

        // Client role users can access it
        if ($user->hasRole('client')) {
            return $next($request);
        }

        // Project managers and developers are blocked unless they also have client role or client_id
        abort(403, 'Unauthorized access to client portal.');
    }
}
