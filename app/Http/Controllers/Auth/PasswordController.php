<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * @group Autenticação
 * Recuperação e alteração de senha.
 */
class PasswordController extends Controller
{
    public function esqueciSenha(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        // Sempre retorna sucesso (não revela se o e-mail existe — segurança)
        return response()->json([
            'message' => 'Se o e-mail estiver cadastrado, você receberá as instruções de redefinição.',
        ]);
    }

    public function redefinirSenha(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->update(['password' => Hash::make($password)]);
                $user->tokens()->delete(); // invalida sessões existentes
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Token inválido ou expirado.'], 422);
        }

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }

    public function alterarSenha(Request $request): JsonResponse
    {
        $request->validate([
            'senha_atual' => ['required'],
            'nova_senha'  => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        $user = $request->user();

        if (! Hash::check($request->string('senha_atual'), $user->password)) {
            return response()->json(['message' => 'Senha atual incorreta.'], 422);
        }

        $user->update(['password' => Hash::make($request->string('nova_senha'))]);

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }
}
