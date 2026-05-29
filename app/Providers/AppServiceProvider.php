<?php

namespace App\Providers;

use App\Services\Fiscal\Contracts\FiscalDocumentInterface;
use App\Services\Fiscal\NFeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind interface fiscal → NFeService (padrão)
        $this->app->bind(FiscalDocumentInterface::class, NFeService::class);
    }

    public function boot(): void
    {
        // Log de queries lentas (> 2s) em produção
        if (! app()->environment('testing')) {
            DB::listen(function ($query) {
                if ($query->time > 2000) {
                    Log::warning('Query lenta detectada', [
                        'sql'      => $query->sql,
                        'time_ms'  => $query->time,
                        'bindings' => $query->bindings,
                    ]);
                }
            });
        }

        // Força HTTPS em produção
        if (app()->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
