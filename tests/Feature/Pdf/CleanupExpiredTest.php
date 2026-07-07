<?php

use App\Models\PdfJob;
use Illuminate\Support\Facades\Storage;

test('pdf:cleanup-expired deletes expired jobs and their files, keeps live ones', function () {
    Storage::disk('local')->put('pdf-jobs/expired/in.pdf', 'x');
    Storage::disk('local')->put('pdf-jobs/expired/out.pdf', 'y');
    Storage::disk('local')->put('pdf-jobs/live/in.pdf', 'z');

    $expired = PdfJob::create([
        'session_id' => 'expired',
        'input_file' => 'pdf-jobs/expired/in.pdf',
        'output_file' => 'pdf-jobs/expired/out.pdf',
        'operation' => 'compress',
        'status' => 'completed',
        'expires_at' => now()->subDay(),
    ]);

    $live = PdfJob::create([
        'session_id' => 'live',
        'input_file' => 'pdf-jobs/live/in.pdf',
        'operation' => 'compress',
        'status' => 'pending',
        'expires_at' => now()->addDay(),
    ]);

    $this->artisan('pdf:cleanup-expired')
        ->expectsOutputToContain('Deleted 1 expired PDF job(s).')
        ->assertSuccessful();

    expect(PdfJob::find($expired->id))->toBeNull();
    expect(PdfJob::find($live->id))->not->toBeNull();

    Storage::disk('local')->assertMissing('pdf-jobs/expired/in.pdf');
    Storage::disk('local')->assertMissing('pdf-jobs/expired/out.pdf');
    Storage::disk('local')->assertExists('pdf-jobs/live/in.pdf');
});
