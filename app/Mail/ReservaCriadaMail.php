<?php

namespace App\Mail;

use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservaCriadaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Reserva $reserva, public string $nome, public string $linkCancelamento) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reserva confirmada');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.reserva-criada');
    }
}
