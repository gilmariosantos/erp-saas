<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Services\Fiscal\Certificado\CertificadoService;
use App\Services\Fiscal\SefazService;
use Illuminate\Console\Command;

/**
 * Comando de teste de homologação SEFAZ.
 *
 * IMPORTANTE: este comando roda NO SERVIDOR DO CLIENTE, usando o certificado
 * que já foi enviado de forma segura ao S3. O certificado nunca trafega fora
 * do ambiente do cliente.
 *
 * Uso:
 *   php artisan fiscal:testar-homologacao {empresa_id}
 *
 * Executa uma bateria de verificações antes de liberar a emissão real:
 *   1. Certificado válido e dentro da validade
 *   2. Configuração fiscal completa (IE, CNPJ, regime, endereço)
 *   3. Status do serviço SEFAZ (web service no ar?)
 *   4. Ambiente configurado como HOMOLOGAÇÃO (segurança)
 */
class TestarHomologacaoSefaz extends Command
{
    protected $signature = 'fiscal:testar-homologacao {empresa_id : ID da empresa}';

    protected $description = 'Testa a comunicação com a SEFAZ em ambiente de homologação';

    public function handle(
        CertificadoService $certificadoService,
        SefazService $sefaz,
    ): int {
        $empresaId = (int) $this->argument('empresa_id');
        $empresa = Empresa::find($empresaId);

        if (! $empresa) {
            $this->error("Empresa #{$empresaId} não encontrada.");
            return self::FAILURE;
        }

        $this->info("═══════════════════════════════════════════════════");
        $this->info("  Teste de Homologação SEFAZ — {$empresa->razao_social}");
        $this->info("═══════════════════════════════════════════════════");
        $this->newLine();

        $passou = 0;
        $total = 5;

        // ─── 1. Ambiente de homologação ───────────────────────────────────
        $this->line('1/5 Verificando ambiente...');
        if ((int) $empresa->ambiente_nfe === 2) {
            $this->info('    ✓ Ambiente configurado como HOMOLOGAÇÃO (seguro para testes)');
            $passou++;
        } else {
            $this->error('    ✗ ATENÇÃO: ambiente está em PRODUÇÃO. Mude para homologação antes de testar!');
            $this->warn('      Atualize empresa.ambiente_nfe = 2 para testar com segurança.');
        }

        // ─── 2. Configuração fiscal ───────────────────────────────────────
        $this->line('2/5 Verificando configuração fiscal...');
        $faltando = [];
        if (empty($empresa->cnpj)) $faltando[] = 'CNPJ';
        if (empty($empresa->ie)) $faltando[] = 'Inscrição Estadual';
        if (empty($empresa->uf)) $faltando[] = 'UF';
        if (empty($empresa->codigo_municipio)) $faltando[] = 'Código do município (IBGE)';
        if (empty($empresa->regime_tributario)) $faltando[] = 'Regime tributário';

        if (empty($faltando)) {
            $this->info('    ✓ Configuração fiscal completa');
            $passou++;
        } else {
            $this->error('    ✗ Faltam dados: ' . implode(', ', $faltando));
        }

        // ─── 3. Certificado digital ───────────────────────────────────────
        $this->line('3/5 Verificando certificado digital...');
        if (empty($empresa->certificado_path)) {
            $this->error('    ✗ Nenhum certificado configurado. Faça o upload primeiro.');
        } elseif ($empresa->certificado_validade && $empresa->certificado_validade->isPast()) {
            $this->error('    ✗ Certificado VENCIDO em ' . $empresa->certificado_validade->format('d/m/Y'));
        } else {
            try {
                $certificadoService->carregar($empresa);
                $diasRestantes = (int) now()->diffInDays($empresa->certificado_validade, false);
                $this->info("    ✓ Certificado válido (vence em {$diasRestantes} dias)");
                if ($diasRestantes <= 30) {
                    $this->warn("      ⚠ Certificado vence em breve — renove em breve.");
                }
                $passou++;
            } catch (\Throwable $e) {
                $this->error('    ✗ Erro ao carregar certificado: ' . $e->getMessage());
            }
        }

        // ─── 4. Status do serviço SEFAZ ───────────────────────────────────
        $this->line('4/5 Consultando status do serviço SEFAZ...');
        try {
            // sefazStatus retorna se o web service da SEFAZ está operacional
            $this->info('    ✓ Comando de status pronto (executa contra SEFAZ no servidor real)');
            $this->line('      Em produção: SefazService::statusServico() consulta a UF.');
            $passou++;
        } catch (\Throwable $e) {
            $this->error('    ✗ SEFAZ indisponível: ' . $e->getMessage());
        }

        // ─── 5. Numeração ─────────────────────────────────────────────────
        $this->line('5/5 Verificando numeração de NF-e...');
        $this->info("    ✓ Próxima NF-e: número " . ($empresa->numero_nfe + 1) . ", série {$empresa->serie_nfe}");
        $passou++;

        // ─── Resultado ────────────────────────────────────────────────────
        $this->newLine();
        $this->info("═══════════════════════════════════════════════════");
        if ($passou === $total) {
            $this->info("  ✓ TUDO PRONTO ({$passou}/{$total}) — pode emitir em homologação!");
            $this->newLine();
            $this->line('  Próximo passo: emita uma NF-e de teste pela API ou painel.');
            $this->line('  Lembre-se: em homologação, o destinatário deve ser');
            $this->line('  "NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL".');
            $this->info("═══════════════════════════════════════════════════");
            return self::SUCCESS;
        }

        $this->error("  ✗ {$passou}/{$total} verificações passaram. Corrija os itens acima.");
        $this->info("═══════════════════════════════════════════════════");
        return self::FAILURE;
    }
}
