<?php

namespace App\Mail;

use App\Constants\SituacaoSolicitacaoConstant;
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
        public string $equipamento,
        public int $quantidade,
        public SituacaoSolicitacaoConstant $situacao,
        public ?string $justificativa,
        public ?CarbonInterface $manterAte,
        public ?string $linkManter,
        public ?string $linkCancelar,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match (true) {
            $this->situacao === SituacaoSolicitacaoConstant::APROVADA => 'Seu pedido de equipamento foi aprovado',
            $this->manterAte !== null                                 => 'Equipamento não liberado: confirme se quer manter a reserva',
            default                                                   => 'Seu pedido de equipamento não foi liberado',
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.solicitacao-respondida');
    }
}
