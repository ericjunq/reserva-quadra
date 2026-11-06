<?php

namespace App\Mail;

use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DevolucaoComPendenciaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Reserva $reserva, public array $itens) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pendência na devolução de equipamentos');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.devolucao-com-pendencia');
    }
}
