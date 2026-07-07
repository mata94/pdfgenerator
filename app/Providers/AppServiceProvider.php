<?php

namespace App\Providers;

use App\Domain\Pdf\Repositories\Interfaces\PdfJobRepositoryInterface;
use App\Domain\Pdf\Repositories\PdfJobRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PdfJobRepositoryInterface::class, PdfJobRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Baseline ceiling for the whole api/v1 group.
        RateLimiter::for('api-default', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Upload/convert shell out to LibreOffice/Ghostscript/ImageMagick —
        // each call is expensive, so these get a stricter budget.
        RateLimiter::for('pdf-conversion', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('pdf-download', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Protects against email-bombing a stranger's inbox via the magic-link form.
        RateLimiter::for('magic-link', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
