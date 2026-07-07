<?php

namespace App\Http\Middleware;

use App\Models\GuestUsage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckGuestLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            return $next($request);
        }

        $usage = GuestUsage::firstOrCreate(
            ['session_id' => session()->getId()],
            ['usage_count' => 0]
        );

        if ($usage->usage_count >= 3) {
            return response()->json([
                'error'   => 'guest_limit_reached',
                'message' => 'Please log in to continue.',
            ], 403);
        }

        return $next($request);
    }
}
