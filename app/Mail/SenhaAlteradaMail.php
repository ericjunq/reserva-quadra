<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SenhaAlteradaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $nome, public Carbon $quando) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Sua senha foi alterada');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.senha-alterada');
    }
}
