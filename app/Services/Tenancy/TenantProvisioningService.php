<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\TenantProvisioningLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Provisiona novos tenants no auto-registro (self-service).
 *
 * Fluxo completo:
 *  1. Cria registro do tenant no banco central (landlord)
 *  2. Cria o banco de dados isolado (tenant_xxx)
 *  3. Roda as migrations do tenant
 *  4. Roda os seeders (unidades, plano de contas, papéis/permissões)
 *  5. Cria o usuário admin inicial da empresa
 *  6. Cria a assinatura em período de trial
 *
 * Cada etapa é registrada em tenant_provisioning_logs para rastreabilidade.
 * Em caso de falha, faz rollback do banco criado.
 */
class TenantProvisioningService
{
    /** Dias de trial gratuito */
    private const TRIAL_DIAS = 14;

    /**
     * Provisiona um novo tenant a partir dos dados de registro.
     *
     * @param array{
     *   razao_social: string,
     *   cnpj: string,
     *   email: string,
     *   nome_responsavel: string,
     *   senha: string,
     *   subdominio: string,
     *   plano_slug?: string,
     * } $dados
     */
    public function provisionar(array $dados): Tenant
    {
        $subdominio = $this->normalizarSubdominio($dados['subdominio']);
        $this->validarSubdominio($subdominio);

        $log = TenantProvisioningLog::create([
            'email_responsavel' => $dados['email'],
            'razao_social'      => $dados['razao_social'],
            'cnpj'              => $dados['cnpj'] ?? null,
            'status'            => 'iniciado',
        ]);

        $tenant = null;

        try {
            // 1. Cria o tenant no banco central
            $tenant = Tenant::create([
                'id'                => $subdominio,
                'email_responsavel' => $dados['email'],
                'razao_social'      => $dados['razao_social'],
                'cnpj'              => $dados['cnpj'] ?? null,
                'status'            => 'provisionando',
            ]);

            // Domínio (subdominio.erpsaas.com.br)
            $tenant->domains()->create([
                'domain' => $subdominio . '.' . config('app.domain_base', 'erpsaas.com.br'),
            ]);

            $log->update(['tenant_id' => $tenant->id, 'status' => 'banco_criado']);

            // 2-4. Roda migrations e seeders no contexto do tenant
            // (stancl/tenancy cria o banco automaticamente via event)
            $tenant->run(function () use ($dados, $log) {
                \Illuminate\Support\Facades\Artisan::call('migrate', [
                    '--path'     => 'database/migrations/tenant',
                    '--force'    => true,
                ]);
                $log->update(['status' => 'migrado']);

                \Illuminate\Support\Facades\Artisan::call('db:seed', [
                    '--class' => 'TenantDatabaseSeeder',
                    '--force' => true,
                ]);

                // Cria papéis e permissões padrão
                app(RoleSeederService::class)->criarPadroes();

                $log->update(['status' => 'seeded']);

                // 5. Cria a empresa e o usuário admin inicial
                $this->criarEmpresaEUsuarioInicial($dados);
            });

            // 6. Cria assinatura trial
            $this->criarAssinaturaTrial($tenant, $dados['plano_slug'] ?? 'pro');

            $tenant->update(['status' => 'ativo']);
            $log->update(['status' => 'concluido']);

            Log::info('Tenant provisionado com sucesso', [
                'tenant_id' => $tenant->id,
                'email'     => $dados['email'],
            ]);

            return $tenant;

        } catch (Throwable $e) {
            $log->update(['status' => 'falhou', 'erro' => $e->getMessage()]);

            // Rollback: remove o tenant e o banco criado
            if ($tenant) {
                try {
                    $tenant->delete(); // stancl/tenancy dropa o banco automaticamente
                } catch (Throwable $rollbackError) {
                    Log::error('Erro no rollback do tenant', [
                        'tenant_id' => $tenant->id,
                        'erro'      => $rollbackError->getMessage(),
                    ]);
                }
            }

            Log::error('Falha ao provisionar tenant', [
                'email' => $dados['email'],
                'erro'  => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                'Não foi possível criar sua conta. Nossa equipe foi notificada. Tente novamente em instantes.',
                previous: $e
            );
        }
    }

    /**
     * Suspende um tenant (ex: inadimplência).
     */
    public function suspender(Tenant $tenant, string $motivo): void
    {
        $tenant->update([
            'status'            => 'suspenso',
            'suspenso_em'       => now(),
            'motivo_suspensao'  => $motivo,
        ]);

        Log::info('Tenant suspenso', ['tenant_id' => $tenant->id, 'motivo' => $motivo]);
    }

    /**
     * Reativa um tenant suspenso.
     */
    public function reativar(Tenant $tenant): void
    {
        $tenant->update([
            'status'           => 'ativo',
            'suspenso_em'      => null,
            'motivo_suspensao' => null,
        ]);

        Log::info('Tenant reativado', ['tenant_id' => $tenant->id]);
    }

    // ─── Privados ─────────────────────────────────────────────────────────────

    private function criarEmpresaEUsuarioInicial(array $dados): void
    {
        $empresa = \App\Models\Empresa::create([
            'razao_social'      => $dados['razao_social'],
            'cnpj'              => $dados['cnpj'] ?? null,
            'regime_tributario' => '1',
            'is_active'         => true,
            'is_matriz'         => true,
        ]);

        $user = \App\Models\User::create([
            'name'      => $dados['nome_responsavel'],
            'email'     => $dados['email'],
            'password'  => Hash::make($dados['senha']),
            'is_active' => true,
            'is_admin'  => true,
            'email_verified_at' => now(),
        ]);

        $user->empresas()->attach($empresa->id, ['is_default' => true]);

        // Atribui papel de administrador
        $user->assignRole('administrador');
    }

    private function criarAssinaturaTrial(Tenant $tenant, string $planoSlug): void
    {
        $plano = DB::table('plans')->where('slug', $planoSlug)->first();

        DB::table('tenant_subscriptions')->insert([
            'tenant_id'            => $tenant->id,
            'plan_id'              => $plano->id ?? 1,
            'status'               => 'trial',
            'trial_ends_at'        => now()->addDays(self::TRIAL_DIAS)->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end'   => now()->addDays(self::TRIAL_DIAS)->toDateString(),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    private function normalizarSubdominio(string $subdominio): string
    {
        $sub = Str::slug($subdominio);
        return preg_replace('/[^a-z0-9-]/', '', $sub);
    }

    private function validarSubdominio(string $subdominio): void
    {
        $reservados = ['www', 'app', 'api', 'admin', 'painel', 'mail', 'ftp', 'staging', 'dev', 'test'];

        if (strlen($subdominio) < 3) {
            throw new \InvalidArgumentException('O subdomínio deve ter ao menos 3 caracteres.');
        }

        if (in_array($subdominio, $reservados)) {
            throw new \InvalidArgumentException("O subdomínio '{$subdominio}' é reservado. Escolha outro.");
        }

        if (Tenant::where('id', $subdominio)->exists()) {
            throw new \InvalidArgumentException("O subdomínio '{$subdominio}' já está em uso. Escolha outro.");
        }
    }
}
