<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @group Admin (Super-Admin)
 * Autenticação dos operadores do SaaS.
 */
class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $admin = AdminUser::where('email', $request->string('email'))->first();

        if (! $admin || ! Hash::check($request->string('password'), $admin->password)) {
            throw ValidationException::withMessages(['email' => ['Credenciais inválidas.']]);
        }

        if (! $admin->is_active) {
            throw ValidationException::withMessages(['email' => ['Conta desativada.']]);
        }

        $admin->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);

        $token = $admin->createToken('admin-token', ['admin'], now()->addDay())->plainTextToken;

        return response()->json([
            'message' => 'Login admin realizado.',
            'token'   => $token,
            'admin'   => $admin->only(['id', 'name', 'email', 'role']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado.']);
    }
}
