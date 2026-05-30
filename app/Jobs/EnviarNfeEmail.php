<?php

namespace App\Jobs;

use App\Models\Nfe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class EnviarNfeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Nfe    $nfe,
        public readonly string $email,
    ) {}

    public function handle(): void
    {
        $xmlPath = $this->nfe->path_xml;
        $pdfPath = $this->nfe->path_pdf;

        Mail::send([], [], function ($message) use ($xmlPath, $pdfPath) {
            $message->to($this->email)
                ->subject("NF-e nº {$this->nfe->numero} — {$this->nfe->empresa->razao_social}")
                ->setBody(
                    "Segue em anexo a NF-e nº {$this->nfe->numero}.\n\nChave: {$this->nfe->chave_acesso}",
                    'text/plain'
                );

            if ($xmlPath && Storage::disk('s3')->exists($xmlPath)) {
                $message->attachData(
                    Storage::disk('s3')->get($xmlPath),
                    "nfe_{$this->nfe->chave_acesso}.xml",
                    ['mime' => 'application/xml']
                );
            }

            if ($pdfPath && Storage::disk('s3')->exists($pdfPath)) {
                $message->attachData(
                    Storage::disk('s3')->get($pdfPath),
                    "danfe_{$this->nfe->chave_acesso}.pdf",
                    ['mime' => 'application/pdf']
                );
            }
        });
    }
}
