<?php

namespace App\Jobs;

use App\Models\Nfe;
use App\Services\Fiscal\NFeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmitirNfe implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;
    public int $backoff = 60;

    public function __construct(public readonly Nfe $nfe) {}

    public function handle(NFeService $service): void
    {
        $service->emitir($this->nfe);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Job EmitirNfe falhou', [
            'nfe_id' => $this->nfe->id,
            'error'  => $exception->getMessage(),
            'tries'  => $this->attempts(),
        ]);
    }
}
