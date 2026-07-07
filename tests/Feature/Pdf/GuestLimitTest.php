<?php

use App\Http\Middleware\CheckGuestLimit;
use App\Http\Middleware\IncrementGuestUsage;
use App\Models\GuestUsage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/*
| The guest-limit gate is keyed on the session id. Laravel's HTTP test client
| rotates the session id on every request (the array session driver doesn't
| persist across requests), so the full upload→convert→download HTTP flow
| can't be threaded here — it is instead browser-verified end to end. These
| tests drive the two middleware classes directly with a pinned session id,
| which is where the actual gate logic lives.
|
| Session ids must be 40-char alphanumeric or Session::setId() silently
| discards them and generates a fresh one.
*/

function sessionId(): string
{
    return Str::random(40);
}

function requestWithSession(string $sessionId): Request
{
    session()->setId($sessionId);
    session()->start();

    $request = Request::create('/api/v1/pdf/convert', 'POST');
    $request->setLaravelSession(session()->driver());

    return $request;
}

test('CheckGuestLimit blocks a guest already at 3 uses', function () {
    $id = sessionId();
    GuestUsage::create(['session_id' => $id, 'usage_count' => 3]);

    $response = (new CheckGuestLimit())->handle(
        requestWithSession($id),
        fn () => response()->json(['ok' => true])
    );

    expect($response->getStatusCode())->toBe(403);
    expect($response->getData(true))->toMatchArray(['error' => 'guest_limit_reached']);
});

test('CheckGuestLimit lets a guest under the limit through', function () {
    $id = sessionId();
    GuestUsage::create(['session_id' => $id, 'usage_count' => 2]);

    $response = (new CheckGuestLimit())->handle(
        requestWithSession($id),
        fn () => response()->json(['ok' => true])
    );

    expect($response->getStatusCode())->toBe(200);
});

test('CheckGuestLimit never blocks an authenticated user', function () {
    $this->actingAs(User::factory()->create());
    $id = sessionId();
    GuestUsage::create(['session_id' => $id, 'usage_count' => 99]);

    $response = (new CheckGuestLimit())->handle(
        requestWithSession($id),
        fn () => response()->json(['ok' => true])
    );

    expect($response->getStatusCode())->toBe(200);
});

test('IncrementGuestUsage bumps the count on a successful response', function () {
    $id = sessionId();
    GuestUsage::create(['session_id' => $id, 'usage_count' => 1]);

    (new IncrementGuestUsage())->handle(
        requestWithSession($id),
        fn () => response()->json(['ok' => true], 200)
    );

    expect(GuestUsage::where('session_id', $id)->first()->usage_count)->toBe(2);
});

test('IncrementGuestUsage does not bump on a failed response', function () {
    $id = sessionId();
    GuestUsage::create(['session_id' => $id, 'usage_count' => 1]);

    (new IncrementGuestUsage())->handle(
        requestWithSession($id),
        fn () => response()->json(['error' => 'nope'], 422)
    );

    expect(GuestUsage::where('session_id', $id)->first()->usage_count)->toBe(1);
});

test('IncrementGuestUsage does not bump for an authenticated user', function () {
    $this->actingAs(User::factory()->create());
    $id = sessionId();
    GuestUsage::create(['session_id' => $id, 'usage_count' => 1]);

    (new IncrementGuestUsage())->handle(
        requestWithSession($id),
        fn () => response()->json(['ok' => true], 200)
    );

    expect(GuestUsage::where('session_id', $id)->first()->usage_count)->toBe(1);
});
