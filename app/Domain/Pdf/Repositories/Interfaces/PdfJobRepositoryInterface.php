<?php

namespace App\Domain\Pdf\Repositories\Interfaces;

use App\Models\PdfJob;
use Illuminate\Database\Eloquent\Collection;

interface PdfJobRepositoryInterface
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): PdfJob;

    public function find(int $id): ?PdfJob;

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(PdfJob $job, array $attributes): PdfJob;

    /**
     * @return Collection<int, PdfJob>
     */
    public function forUser(int $userId): Collection;
}
