<?php

namespace App\Console\Commands;

use App\Models\PdfJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredPdfJobs extends Command
{
    protected $signature = 'pdf:cleanup-expired';

    protected $description = 'Delete expired PDF jobs and their stored input/output files';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $expiredJobs = PdfJob::where('expires_at', '<', now())->get();

        foreach ($expiredJobs as $job) {
            if ($job->input_file) {
                $disk->delete($job->input_file);
            }

            if ($job->output_file) {
                $disk->delete($job->output_file);
            }

            $job->delete();
        }

        $this->info("Deleted {$expiredJobs->count()} expired PDF job(s).");

        return self::SUCCESS;
    }
}
