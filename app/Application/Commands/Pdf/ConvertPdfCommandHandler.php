<?php

namespace App\Application\Commands\Pdf;

use App\Domain\Pdf\Services\PdfConversionService;
use App\Presentation\Pdf\Builders\PdfJobBuilder;
use App\Presentation\Pdf\Models\PdfJobModel;

class ConvertPdfCommandHandler
{
    public function __construct(
        private PdfConversionService $pdfConversionService,
        private PdfJobBuilder $builder
    ) {
    }

    public function execute(ConvertPdfCommand $command): PdfJobModel
    {
        $pdfJob = $this->pdfConversionService->convert($command);

        return $this->builder->makeSingle($pdfJob);
    }
}
