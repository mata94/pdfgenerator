<?php

test('the pdf-conversion limiter returns a JSON 429 after 15 requests a minute', function () {
    $lastStatus = null;

    // upload uses the pdf-conversion limiter (15/min). Requests fail
    // validation (422) but still count against the limiter.
    for ($i = 1; $i <= 16; $i++) {
        $lastStatus = $this->postJson('/api/v1/pdf/upload', [])->getStatusCode();
    }

    expect($lastStatus)->toBe(429);

    $this->postJson('/api/v1/pdf/upload', [])
        ->assertStatus(429)
        ->assertJson(['error' => 'Too Many Attempts.']);
});
