<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use SerializesModels;

    public string $loginUrl;

    public function __construct(
        public string $token,
        public string $email,
        public string $redirectTo = '/',
    ) {
        $this->loginUrl = route('auth.magic-link.login', $this->token);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your PDF Generator login link',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.magic-link',
        );
    }
}
