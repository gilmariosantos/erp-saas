<?php

namespace App\Jobs;

use App\Models\Cte;
use App\Services\Fiscal\CTeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class EmitirCte implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;
    public int $backoff = 60;

    public function __construct(public readonly Cte $cte) {}

    public function handle(CTeService $service): void
    {
        $service->emitir($this->cte);
    }

    public function failed(Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error('Job EmitirCte falhou', [
            'cte_id' => $this->cte->id,
            'error'  => $exception->getMessage(),
        ]);
    }
}
