<?php

use App\Models\PdfJob;

test('guest can upload a pdf and a pending job is created', function () {
    $response = $this->postJson('/api/v1/pdf/upload', [
        'file' => fakePdfUpload(),
        'operation' => 'pdf_to_word',
    ]);

    $response->assertOk()->assertJson([
        'status' => 'pending',
        'operation' => 'pdf_to_word',
    ]);

    expect(PdfJob::count())->toBe(1);
    expect(PdfJob::first()->input_file)->not->toBeNull();
});

test('upload requires a file', function () {
    $this->postJson('/api/v1/pdf/upload', ['operation' => 'pdf_to_word'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

test('upload rejects an invalid operation', function () {
    $this->postJson('/api/v1/pdf/upload', [
        'file' => fakePdfUpload(),
        'operation' => 'not_a_real_operation',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('operation');
});
