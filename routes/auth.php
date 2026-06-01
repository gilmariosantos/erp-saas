<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas de Autenticação (contexto de tenant)
|--------------------------------------------------------------------------
*/

Route::prefix('api/auth')->group(function () {
    // Públicas
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('esqueci-senha', [PasswordController::class, 'esqueciSenha'])->middleware('throttle:5,1');
    Route::post('redefinir-senha', [PasswordController::class, 'redefinirSenha'])->middleware('throttle:5,1');

    // 2FA durante login (usa token temporário)
    Route::post('2fa/validar', [TwoFactorController::class, 'validar'])
        ->middleware(['auth:sanctum', 'throttle:10,1']);

    // Autenticadas
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me',      [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-todos', [AuthController::class, 'logoutTodosDispositivos']);
        Route::post('alterar-senha', [PasswordController::class, 'alterarSenha']);

        // Gestão de 2FA
        Route::post('2fa/ativar',    [TwoFactorController::class, 'ativar']);
        Route::post('2fa/confirmar', [TwoFactorController::class, 'confirmar']);
        Route::post('2fa/desativar', [TwoFactorController::class, 'desativar']);
    });
});

// ─── Assinatura (contexto tenant, autenticado) ───────────────────────────
Route::prefix('api/v1/assinatura')->middleware('auth:sanctum')->group(function () {
    Route::get('status',   [\App\Http\Controllers\Tenant\Billing\AssinaturaController::class, 'status']);
    Route::get('faturas',  [\App\Http\Controllers\Tenant\Billing\AssinaturaController::class, 'faturas']);
    Route::post('cobranca',[\App\Http\Controllers\Tenant\Billing\AssinaturaController::class, 'gerarCobranca']);
    Route::post('cancelar',[\App\Http\Controllers\Tenant\Billing\AssinaturaController::class, 'cancelar']);
});

// ─── Certificado digital (contexto tenant) ───────────────────────────────
Route::prefix('api/v1/fiscal/certificado')->middleware('auth:sanctum')->group(function () {
    Route::post('validar', [\App\Http\Controllers\Tenant\Fiscal\CertificadoController::class, 'validar']);
    Route::post('upload',  [\App\Http\Controllers\Tenant\Fiscal\CertificadoController::class, 'upload']);
    Route::get('{empresa}/info', [\App\Http\Controllers\Tenant\Fiscal\CertificadoController::class, 'info']);
    Route::delete('{empresa}', [\App\Http\Controllers\Tenant\Fiscal\CertificadoController::class, 'remover']);
});
