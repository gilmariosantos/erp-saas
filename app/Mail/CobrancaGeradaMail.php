<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Cobrança gerada — enviado quando uma fatura é criada (PIX/boleto).
 */
class CobrancaGeradaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nomeResponsavel,
        public string $numeroFatura,
        public float $valor,
        public string $vencimento,
        public string $metodoPagamento,
        public ?string $linkPagamento = null,
        public ?string $pixCopiaCola = null,
        public ?string $linhaDigitavel = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Fatura {$this->numeroFatura} — NotaGestão");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.cobranca-gerada');
    }
}
