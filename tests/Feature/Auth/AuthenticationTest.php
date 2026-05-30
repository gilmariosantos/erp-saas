<?php

use App\Models\User;
use App\Services\Tenancy\RoleSeederService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ─── Login ────────────────────────────────────────────────────────────────────

describe('POST /api/auth/login', function () {

    it('autentica usuário com credenciais válidas', function () {
        $user = User::factory()->create([
            'email'     => 'teste@empresa.com',
            'password'  => Hash::make('SenhaForte123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'teste@empresa.com',
            'password' => 'SenhaForte123',
        ]);

        $response->assertOk()
                 ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'roles', 'permissions']]);
    });

    it('rejeita credenciais inválidas', function () {
        User::factory()->create([
            'email'    => 'teste@empresa.com',
            'password' => Hash::make('SenhaForte123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'teste@empresa.com',
            'password' => 'senhaerrada',
        ]);

        $response->assertStatus(422);
    });

    it('rejeita usuário desativado', function () {
        User::factory()->create([
            'email'     => 'inativo@empresa.com',
            'password'  => Hash::make('SenhaForte123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'inativo@empresa.com',
            'password' => 'SenhaForte123',
        ]);

        $response->assertStatus(422);
    });

    it('atualiza last_login_at ao autenticar', function () {
        $user = User::factory()->create([
            'email'         => 'teste@empresa.com',
            'password'      => Hash::make('SenhaForte123'),
            'last_login_at' => null,
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'teste@empresa.com',
            'password' => 'SenhaForte123',
        ]);

        expect($user->fresh()->last_login_at)->not->toBeNull();
    });

});

// ─── Logout ─────────────────────────────────────────────────────────────────

describe('POST /api/auth/logout', function () {

    it('revoga o token atual', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                         ->postJson('/api/auth/logout');

        $response->assertOk();
    });

});

// ─── Alteração de senha ───────────────────────────────────────────────────────

describe('POST /api/auth/alterar-senha', function () {

    it('altera a senha com senha atual correta', function () {
        $user = User::factory()->create(['password' => Hash::make('SenhaAntiga123')]);

        $response = $this->actingAs($user)->postJson('/api/auth/alterar-senha', [
            'senha_atual'           => 'SenhaAntiga123',
            'nova_senha'            => 'SenhaNova456',
            'nova_senha_confirmation'=> 'SenhaNova456',
        ]);

        $response->assertOk();
        expect(Hash::check('SenhaNova456', $user->fresh()->password))->toBeTrue();
    });

    it('rejeita alteração com senha atual incorreta', function () {
        $user = User::factory()->create(['password' => Hash::make('SenhaAntiga123')]);

        $response = $this->actingAs($user)->postJson('/api/auth/alterar-senha', [
            'senha_atual'            => 'errada',
            'nova_senha'             => 'SenhaNova456',
            'nova_senha_confirmation'=> 'SenhaNova456',
        ]);

        $response->assertStatus(422);
    });

});

// ─── Papéis e Permissões ──────────────────────────────────────────────────────

describe('RoleSeederService', function () {

    it('cria todos os papéis padrão', function () {
        app(RoleSeederService::class)->criarPadroes();

        expect(\Spatie\Permission\Models\Role::count())->toBe(5);
        expect(\Spatie\Permission\Models\Role::where('name', 'administrador')->exists())->toBeTrue();
    });

    it('administrador recebe todas as permissões', function () {
        app(RoleSeederService::class)->criarPadroes();

        $admin = \Spatie\Permission\Models\Role::where('name', 'administrador')->first();
        $totalPermissoes = \Spatie\Permission\Models\Permission::count();

        expect($admin->permissions->count())->toBe($totalPermissoes);
    });

    it('vendedor não tem permissão de emitir NF-e', function () {
        app(RoleSeederService::class)->criarPadroes();

        $vendedor = \Spatie\Permission\Models\Role::where('name', 'vendedor')->first();

        expect($vendedor->hasPermissionTo('nfe.emitir'))->toBeFalse()
            ->and($vendedor->hasPermissionTo('vendas.criar'))->toBeTrue();
    });

    it('fiscal tem permissões de emissão mas não de vendas', function () {
        app(RoleSeederService::class)->criarPadroes();

        $fiscal = \Spatie\Permission\Models\Role::where('name', 'fiscal')->first();

        expect($fiscal->hasPermissionTo('nfe.emitir'))->toBeTrue()
            ->and($fiscal->hasPermissionTo('cte.emitir'))->toBeTrue()
            ->and($fiscal->hasPermissionTo('vendas.faturar'))->toBeFalse();
    });

    it('atribui papel a usuário e verifica permissão', function () {
        app(RoleSeederService::class)->criarPadroes();

        $user = User::factory()->create();
        $user->assignRole('financeiro');

        expect($user->hasRole('financeiro'))->toBeTrue()
            ->and($user->can('financeiro.baixar'))->toBeTrue()
            ->and($user->can('nfe.emitir'))->toBeFalse();
    });

});
