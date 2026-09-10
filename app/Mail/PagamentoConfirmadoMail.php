<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Pagamento confirmado — enviado quando o webhook confirma o pagamento.
 */
class PagamentoConfirmadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nomeResponsavel,
        public string $numeroFatura,
        public float $valor,
        public string $planoNome,
        public string $proximaCobranca,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pagamento confirmado — obrigado! ✓');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.pagamento-confirmado');
    }
}
