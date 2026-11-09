<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AlertaSistemaMail extends Mailable
{
    public function __construct(public string $assunto, public string $descricao, public Carbon $quando) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Alerta] '.$this->assunto);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.alerta-sistema');
    }
}
