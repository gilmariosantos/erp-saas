<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso de trial expirando — enviado 3 dias e 1 dia antes do fim.
 */
class TrialExpirandoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nomeResponsavel,
        public int $diasRestantes,
        public string $urlAssinatura,
    ) {}

    public function envelope(): Envelope
    {
        $assunto = $this->diasRestantes <= 1
            ? 'Seu teste grátis termina amanhã'
            : "Seu teste grátis termina em {$this->diasRestantes} dias";
        return new Envelope(subject: $assunto);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.trial-expirando');
    }
}
