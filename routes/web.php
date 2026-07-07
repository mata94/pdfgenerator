<?php

use App\Domain\Pdf\Repositories\Interfaces\PdfJobRepositoryInterface;
use App\Http\Controllers\Api\V1\GuestController;
use App\Http\Controllers\Api\V1\PdfController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Presentation\Pdf\Builders\PdfJobBuilder;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Auth
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
Route::post('/auth/magic-link', [MagicLinkController::class, 'send'])
    ->middleware('throttle:magic-link')
    ->name('auth.magic-link.send');
Route::get('/auth/magic-link/{token}', [MagicLinkController::class, 'login'])->name('auth.magic-link.login');
Route::post('/auth/logout', [MagicLinkController::class, 'logout'])->name('auth.logout')->middleware('auth');

// Inertia Pages
Route::get('/', fn () => Inertia::render('Home'))->name('home');
Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
Route::get('/dashboard', function (PdfJobRepositoryInterface $repository, PdfJobBuilder $builder) {
    return Inertia::render('Dashboard', [
        'jobs' => $builder->makeCollection($repository->forUser(auth()->id())),
    ]);
})->middleware('auth')->name('dashboard');

// API V1
Route::prefix('api/v1')->name('api.v1.')->middleware('throttle:api-default')->group(function () {
    Route::post('/pdf/upload', [PdfController::class, 'upload'])
        ->middleware('throttle:pdf-conversion')
        ->name('pdf.upload');

    Route::post('/pdf/convert', [PdfController::class, 'convert'])
        ->middleware(['check.guest.limit', 'throttle:pdf-conversion'])
        ->name('pdf.convert');

    Route::get('/pdf/{id}', [PdfController::class, 'show'])
        ->name('pdf.show');

    Route::post('/pdf/{id}/download', [PdfController::class, 'download'])
        ->middleware(['check.guest.limit', 'increment.guest.usage', 'throttle:pdf-download'])
        ->name('pdf.download');

    Route::post('/guest/email', [GuestController::class, 'saveEmail'])
        ->name('guest.email');
});
