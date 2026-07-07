<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GuestUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    public function saveEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        GuestUsage::where('session_id', session()->getId())
            ->update(['email' => strtolower($request->email)]);

        return response()->json(['success' => true]);
    }
}
