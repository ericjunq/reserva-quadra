<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RedefinicaoSenhaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $nome, public string $link, public Carbon $expiraEm) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Redefinição de senha');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.redefinicao-senha');
    }
}
