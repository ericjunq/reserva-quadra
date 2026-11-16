<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CodigoLoginMail extends Mailable
{
    public function __construct(public string $nome, public string $codigo, public int $minutos) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Seu código de acesso');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.codigo-login');
    }
}
