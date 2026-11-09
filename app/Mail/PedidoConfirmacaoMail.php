<?php

namespace App\Mail;

use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PedidoConfirmacaoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reserva $reserva,
        public string $nome,
        public string $linkConfirmacao,
        public string $linkCancelamento,
        public Carbon $prazo,
        public bool $recemCriada = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirme sua reserva');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.pedido-confirmacao');
    }
}
