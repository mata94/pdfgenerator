<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $email = strtolower($request->email);
        $plain = Str::random(64);
        $redirect = $request->input('redirect_to', '/');

        LoginToken::create([
            'email' => $email,
            'token' => hash('sha256', $plain),
            'expires_at' => Carbon::now()->addMinutes(30),
            'redirect_to' => $redirect,
        ]);

        Mail::to($email)->send(new MagicLinkMail($plain, $email, $redirect));

        return back()->with('status', 'Check your email for the login link.');
    }

    public function login(string $token): RedirectResponse
    {
        $hashed = hash('sha256', $token);

        $loginToken = LoginToken::where('token', $hashed)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $user = User::firstOrCreate(
            ['email' => $loginToken->email],
            ['name' => null]
        );

        $loginToken->update(['used_at' => now()]);

        auth()->login($user);

        return redirect($loginToken->redirect_to ?? '/');
    }

    public function logout(Request $request): RedirectResponse
    {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
