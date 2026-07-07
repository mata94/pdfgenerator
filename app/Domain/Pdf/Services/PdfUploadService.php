<?php

namespace App\Domain\Pdf\Services;

use App\Application\Commands\Pdf\UploadPdfCommand;
use App\Domain\Pdf\Enums\PdfJobStatus;
use App\Domain\Pdf\Repositories\Interfaces\PdfJobRepositoryInterface;
use App\Models\PdfJob;
use Illuminate\Support\Str;

class PdfUploadService
{
    public function __construct(
        private PdfJobRepositoryInterface $repository,
    ) {
    }

    public function upload(UploadPdfCommand $command): PdfJob
    {
        $file = $command->getUploadedFile();

        $path = $file->storeAs(
            'pdf-jobs/'.$command->getSessionId(),
            Str::uuid()->toString().'.'.$file->getClientOriginalExtension(),
        );

        return $this->repository->create([
            'user_id' => $command->getUser()?->id,
            'session_id' => $command->getSessionId(),
            'input_file' => $path,
            'operation' => $command->getOperation(),
            'status' => PdfJobStatus::PENDING->value,
            'expires_at' => now()->addHours(24),
        ]);
    }
}
