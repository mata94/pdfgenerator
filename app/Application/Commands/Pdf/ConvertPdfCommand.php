<?php

namespace App\Application\Commands\Pdf;

use App\Models\User;

class ConvertPdfCommand
{
    private int $pdfJobId;
    private string $sessionId;
    private ?User $user = null;

    public function getPdfJobId(): int
    {
        return $this->pdfJobId;
    }

    public function setPdfJobId(int $pdfJobId): void
    {
        $this->pdfJobId = $pdfJobId;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }
}
