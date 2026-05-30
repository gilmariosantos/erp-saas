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
