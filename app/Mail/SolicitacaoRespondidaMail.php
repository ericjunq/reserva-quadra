<?php

namespace App\Mail;

use App\Models\Reserva;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SolicitacaoRespondidaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reserva $reserva,
        public array $itens,
        public bool $naoLiberados,
        public ?CarbonInterface $manterAte,
        public ?string $linkManter,
        public ?string $linkCancelar,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match (true) {
            ! $this->naoLiberados     => 'Seu pedido de equipamento foi aprovado',
            $this->manterAte !== null => 'Equipamento não liberado: confirme se quer manter a reserva',
            default                   => 'Seu pedido de equipamento não foi liberado',
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.solicitacao-respondida');
    }
}
