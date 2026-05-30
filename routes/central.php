<?php

use App\Http\Controllers\Central\RegistroController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\TenantAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas Centrais (domínio principal, fora de tenant)
|--------------------------------------------------------------------------
*/

// ─── Auto-registro (self-service) ────────────────────────────────────────
Route::prefix('api/onboarding')->group(function () {
    Route::post('registrar', [RegistroController::class, 'registrar'])
        ->middleware('throttle:5,1'); // máx 5 registros/min por IP
    Route::get('verificar-subdominio/{subdominio}', [RegistroController::class, 'verificarSubdominio'])
        ->middleware('throttle:30,1');
});

// ─── Painel Super-Admin ──────────────────────────────────────────────────
Route::prefix('api/admin')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('logout',  [AdminAuthController::class, 'logout']);
        Route::get('metricas', [TenantAdminController::class, 'metricas']);

        Route::get('tenants',                    [TenantAdminController::class, 'index']);
        Route::get('tenants/{tenantId}',         [TenantAdminController::class, 'show']);
        Route::post('tenants/{tenantId}/suspender', [TenantAdminController::class, 'suspender']);
        Route::post('tenants/{tenantId}/reativar',  [TenantAdminController::class, 'reativar']);
    });
});
