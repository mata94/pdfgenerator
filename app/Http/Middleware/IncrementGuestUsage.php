<?php

namespace App\Http\Middleware;

use App\Models\GuestUsage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IncrementGuestUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! auth()->check() && $response->isSuccessful()) {
            GuestUsage::where('session_id', session()->getId())
                ->increment('usage_count');
        }

        return $response;
    }
}
