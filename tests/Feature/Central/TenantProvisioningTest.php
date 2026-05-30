<?php

use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * NOTA: Estes testes validam a lógica de validação do provisionamento.
 * O provisionamento completo (criação de banco isolado) requer ambiente
 * com stancl/tenancy configurado e é testado em tests/Integration.
 */

// ─── Validação de subdomínio ──────────────────────────────────────────────────

describe('TenantProvisioningService — validação de subdomínio', function () {

    it('rejeita subdomínio reservado', function () {
        $service = app(TenantProvisioningService::class);

        expect(fn () => $service->provisionar([
            'razao_social'     => 'Teste Ltda',
            'cnpj'             => '11.222.333/0001-81',
            'email'            => 'teste@teste.com',
            'nome_responsavel' => 'João',
            'senha'            => 'SenhaForte123',
            'subdominio'       => 'admin',
        ]))->toThrow(\RuntimeException::class);
    });

    it('rejeita subdomínio muito curto', function () {
        $service = app(TenantProvisioningService::class);

        expect(fn () => $service->provisionar([
            'razao_social'     => 'Teste Ltda',
            'cnpj'             => '11.222.333/0001-81',
            'email'            => 'teste@teste.com',
            'nome_responsavel' => 'João',
            'senha'            => 'SenhaForte123',
            'subdominio'       => 'ab',
        ]))->toThrow(\RuntimeException::class);
    });

});

// ─── Endpoint de verificação de subdomínio ──────────────────────────────────

describe('GET /api/onboarding/verificar-subdominio', function () {

    it('retorna disponível para subdomínio novo', function () {
        $response = $this->getJson('/api/onboarding/verificar-subdominio/minhaempresa');

        $response->assertOk()
                 ->assertJsonPath('disponivel', true);
    });

    it('retorna indisponível para subdomínio reservado', function () {
        $response = $this->getJson('/api/onboarding/verificar-subdominio/admin');

        $response->assertOk()
                 ->assertJsonPath('disponivel', false)
                 ->assertJsonStructure(['sugestao']);
    });

    it('normaliza subdomínio com maiúsculas e espaços', function () {
        $response = $this->getJson('/api/onboarding/verificar-subdominio/Minha Empresa');

        $response->assertOk()
                 ->assertJsonPath('subdominio', 'minha-empresa');
    });

});

// ─── Validação do formulário de registro ──────────────────────────────────────

describe('POST /api/onboarding/registrar — validações', function () {

    it('exige campos obrigatórios', function () {
        $response = $this->postJson('/api/onboarding/registrar', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['razao_social', 'email', 'senha', 'subdominio', 'nome_responsavel']);
    });

    it('valida formato do subdomínio', function () {
        $response = $this->postJson('/api/onboarding/registrar', [
            'razao_social'         => 'Teste Ltda',
            'nome_responsavel'     => 'João Silva',
            'email'                => 'joao@teste.com',
            'senha'                => 'SenhaForte123',
            'senha_confirmation'   => 'SenhaForte123',
            'subdominio'           => 'INVÁLIDO COM ESPAÇO',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('subdominio');
    });

    it('exige senha forte', function () {
        $response = $this->postJson('/api/onboarding/registrar', [
            'razao_social'       => 'Teste Ltda',
            'nome_responsavel'   => 'João Silva',
            'email'              => 'joao@teste.com',
            'senha'              => '123',
            'senha_confirmation' => '123',
            'subdominio'         => 'testeempresa',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('senha');
    });

    it('exige confirmação de senha correspondente', function () {
        $response = $this->postJson('/api/onboarding/registrar', [
            'razao_social'       => 'Teste Ltda',
            'nome_responsavel'   => 'João Silva',
            'email'              => 'joao@teste.com',
            'senha'              => 'SenhaForte123',
            'senha_confirmation' => 'Diferente456',
            'subdominio'         => 'testeempresa',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('senha');
    });

});

// ─── Painel Super-Admin ──────────────────────────────────────────────────────

describe('Painel Admin — autenticação', function () {

    it('admin faz login com credenciais válidas', function () {
        \App\Models\AdminUser::create([
            'name'      => 'Super Admin',
            'email'     => 'admin@erpsaas.com',
            'password'  => 'AdminForte123',
            'role'      => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email'    => 'admin@erpsaas.com',
            'password' => 'AdminForte123',
        ]);

        $response->assertOk()
                 ->assertJsonStructure(['token', 'admin' => ['id', 'name', 'email', 'role']]);
    });

    it('bloqueia admin desativado', function () {
        \App\Models\AdminUser::create([
            'name'      => 'Admin Inativo',
            'email'     => 'inativo@erpsaas.com',
            'password'  => 'AdminForte123',
            'role'      => 'suporte',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email'    => 'inativo@erpsaas.com',
            'password' => 'AdminForte123',
        ]);

        $response->assertStatus(422);
    });

    it('nega acesso a rotas admin sem autenticação', function () {
        $response = $this->getJson('/api/admin/tenants');
        $response->assertStatus(401);
    });

});
