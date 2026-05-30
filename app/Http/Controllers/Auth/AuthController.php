<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @group Autenticação
 * Login, logout e dados do usuário autenticado (contexto de tenant).
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Sua conta está desativada. Contate o administrador.'],
            ]);
        }

        // Se 2FA está ativado, exige o segundo fator antes de emitir token
        if ($user->two_factor_confirmed_at) {
            return response()->json([
                'requires_two_factor' => true,
                'message' => 'Informe o código de autenticação de dois fatores.',
                'temp_token' => $this->emitirTokenTemporario($user),
            ]);
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'empresa' => $user->empresaAtiva()?->only(['id', 'razao_social', 'cnpj']),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'two_factor_enabled' => (bool) $user->two_factor_confirmed_at,
                'empresas' => $user->empresas->map->only(['id', 'razao_social', 'cnpj']),
                'empresa_ativa' => $user->empresaAtiva()?->only(['id', 'razao_social']),
            ],
        ]);
    }

    public function logoutTodosDispositivos(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Desconectado de todos os dispositivos.']);
    }

    private function emitirTokenTemporario(User $user): string
    {
        return $user->createToken('2fa-temp', ['2fa-pending'], now()->addMinutes(10))->plainTextToken;
    }
}
