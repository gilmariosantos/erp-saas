<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

/**
 * @group Autenticação
 * Configuração e validação de autenticação de dois fatores (TOTP).
 * Compatível com Google Authenticator, Authy, etc.
 */
class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Gera o segredo e QR code para ativar 2FA.
     */
    public function ativar(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->google2fa->generateSecretKey();

        $user->update(['two_factor_secret' => Crypt::encryptString($secret)]);

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return response()->json([
            'secret'      => $secret,
            'qr_code_url' => $qrCodeUrl,
            'message'     => 'Escaneie o QR code no seu app autenticador e confirme com um código.',
        ]);
    }

    /**
     * Confirma a ativação do 2FA validando o primeiro código.
     */
    public function confirmar(Request $request): JsonResponse
    {
        $request->validate(['codigo' => ['required', 'string', 'size:6']]);

        $user = $request->user();
        $secret = Crypt::decryptString($user->two_factor_secret);

        if (! $this->google2fa->verifyKey($secret, $request->string('codigo'))) {
            return response()->json(['message' => 'Código inválido.'], 422);
        }

        // Gera códigos de recuperação
        $recoveryCodes = collect(range(1, 8))->map(fn () => \Illuminate\Support\Str::random(10))->toArray();

        $user->update([
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
        ]);

        return response()->json([
            'message'        => '2FA ativado com sucesso. Guarde seus códigos de recuperação.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Valida o código 2FA no login (troca o token temporário pelo definitivo).
     */
    public function validar(Request $request): JsonResponse
    {
        $request->validate(['codigo' => ['required', 'string']]);

        $user = $request->user();
        $secret = Crypt::decryptString($user->two_factor_secret);

        $valido = $this->google2fa->verifyKey($secret, $request->string('codigo'))
            || $this->validarCodigoRecuperacao($user, $request->string('codigo'));

        if (! $valido) {
            return response()->json(['message' => 'Código inválido.'], 422);
        }

        // Remove token temporário e emite o definitivo
        $request->user()->currentAccessToken()->delete();
        $user->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);

        $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'message' => 'Autenticação concluída.',
            'token'   => $token,
        ]);
    }

    /**
     * Desativa o 2FA.
     */
    public function desativar(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required']]);

        $user = $request->user();
        if (! \Illuminate\Support\Facades\Hash::check($request->string('password'), $user->password)) {
            return response()->json(['message' => 'Senha incorreta.'], 422);
        }

        $user->update([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ]);

        return response()->json(['message' => '2FA desativado.']);
    }

    private function validarCodigoRecuperacao($user, string $codigo): bool
    {
        if (! $user->two_factor_recovery_codes) return false;

        $codes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);

        if (in_array($codigo, $codes)) {
            // Remove o código usado (one-time use)
            $codes = array_values(array_diff($codes, [$codigo]));
            $user->update(['two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes))]);
            return true;
        }
        return false;
    }
}
