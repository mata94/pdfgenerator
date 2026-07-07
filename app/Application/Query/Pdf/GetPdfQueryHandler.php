<?php

namespace App\Application\Query\Pdf;

use App\Domain\Pdf\Repositories\Interfaces\PdfJobRepositoryInterface;
use App\Presentation\Pdf\Builders\PdfJobBuilder;
use App\Presentation\Pdf\Models\PdfJobModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetPdfQueryHandler
{
    public function __construct(
        private PdfJobRepositoryInterface $repository,
        private PdfJobBuilder $builder
    ) {
    }

    public function execute(GetPdfQuery $query): PdfJobModel
    {
        $pdfJob = $this->repository->find($query->getId());

        if (! $pdfJob) {
            throw new ModelNotFoundException("PDF job {$query->getId()} not found.");
        }

        return $this->builder->makeSingle($pdfJob);
    }
}
