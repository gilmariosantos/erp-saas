<?php

namespace App\Services\Tenancy;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Cria os papéis e permissões padrão de um tenant recém-provisionado.
 *
 * Papéis:
 *  - administrador : acesso total
 *  - financeiro    : financeiro + relatórios
 *  - vendedor      : vendas + cadastros (leitura)
 *  - fiscal        : emissão de documentos fiscais
 *  - operador      : operação básica de estoque
 */
class RoleSeederService
{
    /** Todas as permissões do sistema agrupadas por módulo */
    private const PERMISSOES = [
        // Cadastros
        'pessoas.ver', 'pessoas.criar', 'pessoas.editar', 'pessoas.excluir',
        'produtos.ver', 'produtos.criar', 'produtos.editar', 'produtos.excluir',
        // Financeiro
        'financeiro.ver', 'financeiro.criar', 'financeiro.editar', 'financeiro.baixar', 'financeiro.excluir',
        // Estoque
        'estoque.ver', 'estoque.movimentar', 'estoque.inventario',
        'compras.ver', 'compras.criar', 'compras.receber',
        // Vendas
        'vendas.ver', 'vendas.criar', 'vendas.aprovar', 'vendas.faturar', 'vendas.cancelar',
        // Fiscal
        'nfe.ver', 'nfe.emitir', 'nfe.cancelar', 'nfe.inutilizar',
        'cte.ver', 'cte.emitir', 'cte.cancelar', 'ciot.gerar',
        'nfse.ver', 'nfse.emitir', 'nfse.cancelar',
        // Relatórios e config
        'dashboard.ver', 'relatorios.ver',
        'usuarios.ver', 'usuarios.gerenciar', 'configuracoes.gerenciar',
    ];

    private const PAPEIS = [
        'administrador' => '*', // todas
        'financeiro' => [
            'dashboard.ver', 'financeiro.ver', 'financeiro.criar', 'financeiro.editar',
            'financeiro.baixar', 'relatorios.ver', 'pessoas.ver',
        ],
        'vendedor' => [
            'dashboard.ver', 'vendas.ver', 'vendas.criar', 'pessoas.ver',
            'pessoas.criar', 'produtos.ver',
        ],
        'fiscal' => [
            'dashboard.ver', 'nfe.ver', 'nfe.emitir', 'nfe.cancelar',
            'cte.ver', 'cte.emitir', 'cte.cancelar', 'ciot.gerar',
            'nfse.ver', 'nfse.emitir', 'nfse.cancelar', 'produtos.ver', 'pessoas.ver',
        ],
        'operador' => [
            'dashboard.ver', 'estoque.ver', 'estoque.movimentar',
            'compras.ver', 'compras.receber', 'produtos.ver',
        ],
    ];

    public function criarPadroes(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        // Cria todas as permissões
        foreach (self::PERMISSOES as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Cria papéis e atribui permissões
        foreach (self::PAPEIS as $papel => $permissoes) {
            $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);

            if ($permissoes === '*') {
                $role->syncPermissions(Permission::all());
            } else {
                $role->syncPermissions($permissoes);
            }
        }
    }
}
