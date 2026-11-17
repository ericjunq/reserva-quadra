<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AceiteApiMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nome,
        public string $sistema,
        public string $link,
        public CarbonInterface $expiraEm,
        public ?CarbonInterface $acessoVenceEm = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->acessoVenceEm
            ? "O acesso do {$this->sistema} à API de reservas vai vencer"
            : "Acesso do {$this->sistema} à API de reservas: aceite dos termos");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.aceite-api');
    }
}
