<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $u = $request->user();
        if (!$u) return redirect()->route('login');
        if (!in_array($u->role, $roles)) abort(403, 'Anda tidak berhak mengakses halaman ini.');
        return $next($request);
    }
}
