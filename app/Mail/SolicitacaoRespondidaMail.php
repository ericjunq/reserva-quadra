<?php

namespace App\Mail;

use App\Constants\SituacaoSolicitacaoConstant;
use App\Models\Reserva;
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
        public string $equipamento,
        public int $quantidade,
        public SituacaoSolicitacaoConstant $situacao,
        public ?string $justificativa,
        public ?string $linkCancelar,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->situacao) {
            SituacaoSolicitacaoConstant::APROVADA => 'Seu pedido de equipamento foi aprovado',
            SituacaoSolicitacaoConstant::RECUSADA => 'Seu pedido de equipamento foi recusado',
            default                               => 'Seu pedido de equipamento ficou sem resposta',
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.solicitacao-respondida');
    }
}
