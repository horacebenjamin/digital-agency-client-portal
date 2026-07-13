<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessClientPortal
{
    private const PORTAL_ACCESS_MESSAGE = 'This account does not have access to the client portal. Please sign in with an authorised client account.';

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

        // Users linked to a client account can access the portal
        if ($user->client_id !== null) {
            return $next($request);
        }

        // Client role users can access it
        if ($user->hasRole('client')) {
            return $next($request);
        }

        if ($user->hasAnyRole(['admin', 'project_manager', 'developer'])) {
            return redirect('/admin')->with('error', self::PORTAL_ACCESS_MESSAGE);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('error', self::PORTAL_ACCESS_MESSAGE);
    }
}
