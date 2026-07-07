<?php

namespace App\Domain\Pdf\Repositories;

use App\Domain\Pdf\Repositories\Interfaces\PdfJobRepositoryInterface;
use App\Models\PdfJob;
use Illuminate\Database\Eloquent\Collection;

class PdfJobRepository implements PdfJobRepositoryInterface
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): PdfJob
    {
        return PdfJob::create($attributes);
    }

    public function find(int $id): ?PdfJob
    {
        return PdfJob::find($id);
    }

    public function update(PdfJob $job, array $attributes): PdfJob
    {
        $job->update($attributes);

        return $job;
    }

    public function forUser(int $userId): Collection
    {
        return PdfJob::where('user_id', $userId)->latest()->get();
    }
}
