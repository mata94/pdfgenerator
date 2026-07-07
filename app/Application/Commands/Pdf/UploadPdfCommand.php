<?php

namespace App\Application\Commands\Pdf;

use App\Models\User;
use Illuminate\Http\UploadedFile;

class UploadPdfCommand
{
    private UploadedFile $uploadedFile;
    private string $operation;
    private string $sessionId;
    private ?User $user = null;

    public function getUploadedFile(): UploadedFile
    {
        return $this->uploadedFile;
    }

    public function setUploadedFile(UploadedFile $uploadedFile): void
    {
        $this->uploadedFile = $uploadedFile;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): void
    {
        $this->operation = $operation;
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
