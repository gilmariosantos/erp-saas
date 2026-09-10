<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de boas-vindas — enviado logo após o registro do tenant.
 */
class BoasVindasMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nomeResponsavel,
        public string $razaoSocial,
        public string $urlSistema,
        public int $diasTrial = 14,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bem-vindo ao NotaGestão! Sua conta está pronta 🎉');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.boas-vindas');
    }
}
