<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $url, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Twój link do listy prezentów');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.login-link');
    }
}
