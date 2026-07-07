<?php

namespace App\Application\Commands\Pdf;

use App\Domain\Pdf\Services\PdfUploadService;
use App\Presentation\Pdf\Builders\PdfJobBuilder;
use App\Presentation\Pdf\Models\PdfJobModel;

class UploadPdfCommandHandler
{
    public function __construct(
        private PdfUploadService $pdfUploadService,
        private PdfJobBuilder $builder
    ) {
    }

    public function execute(UploadPdfCommand $command): PdfJobModel
    {
        $pdfJob = $this->pdfUploadService->upload($command);

        return $this->builder->makeSingle($pdfJob);
    }
}
