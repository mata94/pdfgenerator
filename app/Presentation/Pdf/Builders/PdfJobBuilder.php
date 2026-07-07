<?php

namespace App\Presentation\Pdf\Builders;

use App\Models\PdfJob;
use App\Presentation\Pdf\Models\PdfJobModel;
use Illuminate\Support\Facades\Storage;

class PdfJobBuilder
{
    public function makeSingle(PdfJob $job): PdfJobModel
    {
        $model = new PdfJobModel();
        $model->setId($job->id);
        $model->setStatus($job->status);
        $model->setOperation($job->operation ?? '');
        $model->setDownloadUrl(
            $job->output_file
                ? Storage::temporaryUrl($job->output_file, now()->addHour())
                : null
        );
        $model->setCreatedAt($job->created_at->toIso8601String());

        return $model;
    }

    /**
     * @param iterable<int, PdfJob> $jobs
     * @return array<int, PdfJobModel>
     */
    public function makeCollection(iterable $jobs): array
    {
        return array_map(fn (PdfJob $job) => $this->makeSingle($job), [...$jobs]);
    }
}
