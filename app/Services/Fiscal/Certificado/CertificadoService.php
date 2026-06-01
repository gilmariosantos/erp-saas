<?php

namespace App\Services\Fiscal\Certificado;

use App\Models\Empresa;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use NFePHP\Common\Certificate;

/**
 * Gerencia certificados digitais A1 (.pfx/.p12) com segurança.
 *
 * Princípios de segurança aplicados:
 *  - A senha do certificado é SEMPRE criptografada (Crypt) antes de persistir
 *  - O .pfx é armazenado em disco privado (S3/MinIO), nunca em pasta pública
 *  - A senha nunca é logada, retornada em API ou exposta em mensagens de erro
 *  - A validação ocorre em memória, sem gravar a senha em texto plano
 */
class CertificadoService
{
    /**
     * Valida um certificado e extrai seus dados, SEM persistir ainda.
     * Usado para dar feedback ao usuário antes de salvar.
     *
     * @param string $conteudoPfx Conteúdo binário do .pfx
     * @param string $senha       Senha do certificado (em memória apenas)
     * @return array{
     *   valido: bool,
     *   cnpj: ?string,
     *   razao_social: ?string,
     *   validade: ?string,
     *   dias_para_vencer: ?int,
     *   erro: ?string,
     * }
     */
    public function validar(string $conteudoPfx, string $senha): array
    {
        try {
            $certificado = Certificate::readPfx($conteudoPfx, $senha);

            $validadeFim = $certificado->getValidTo();
            $diasParaVencer = (int) now()->diffInDays($validadeFim, false);

            // Extrai CNPJ do campo do certificado (geralmente no CN ou subjectAltName)
            $cnpj = $this->extrairCnpj($certificado);

            return [
                'valido'           => true,
                'cnpj'             => $cnpj,
                'razao_social'     => $this->extrairRazaoSocial($certificado),
                'validade'         => $validadeFim->format('Y-m-d'),
                'dias_para_vencer' => $diasParaVencer,
                'vencido'          => $diasParaVencer < 0,
                'erro'             => null,
            ];

        } catch (\Throwable $e) {
            // Não vaza detalhes internos — apenas indica falha genérica
            $msg = str_contains(strtolower($e->getMessage()), 'password') || str_contains(strtolower($e->getMessage()), 'mac')
                ? 'Senha do certificado incorreta.'
                : 'Arquivo de certificado inválido ou corrompido.';

            return [
                'valido' => false,
                'cnpj'   => null,
                'razao_social' => null,
                'validade' => null,
                'dias_para_vencer' => null,
                'erro'   => $msg,
            ];
        }
    }

    /**
     * Salva o certificado de forma segura para uma empresa.
     * O .pfx vai criptografado para o S3 e a senha criptografada no banco.
     *
     * @throws \InvalidArgumentException se o certificado for inválido ou o CNPJ não bater
     */
    public function salvar(Empresa $empresa, string $conteudoPfx, string $senha): array
    {
        $validacao = $this->validar($conteudoPfx, $senha);

        if (! $validacao['valido']) {
            throw new \InvalidArgumentException($validacao['erro']);
        }

        if ($validacao['vencido']) {
            throw new \InvalidArgumentException(
                'Certificado vencido em ' . \Carbon\Carbon::parse($validacao['validade'])->format('d/m/Y') . '.'
            );
        }

        // Confere se o CNPJ do certificado bate com o da empresa (proteção contra erro)
        $cnpjEmpresa = preg_replace('/\D/', '', $empresa->cnpj ?? '');
        $cnpjCert    = preg_replace('/\D/', '', $validacao['cnpj'] ?? '');

        if ($cnpjEmpresa && $cnpjCert && $cnpjEmpresa !== $cnpjCert) {
            throw new \InvalidArgumentException(
                "O CNPJ do certificado ({$validacao['cnpj']}) não corresponde ao CNPJ da empresa cadastrada."
            );
        }

        // Armazena o .pfx criptografado em disco privado
        $path = sprintf(
            'certs/%s/cert_%s.pfx.enc',
            $cnpjEmpresa,
            now()->format('YmdHis')
        );

        Storage::disk(config('fiscal.storage.disk', 's3'))
            ->put($path, Crypt::encryptString($conteudoPfx), 'private');

        // Remove certificado anterior, se houver
        if ($empresa->certificado_path) {
            Storage::disk(config('fiscal.storage.disk', 's3'))->delete($empresa->certificado_path);
        }

        $empresa->update([
            'certificado_path'     => $path,
            'certificado_senha'    => $senha, // cast 'encrypted' no model criptografa
            'certificado_validade' => $validacao['validade'],
        ]);

        Log::info('Certificado digital atualizado', [
            'empresa_id' => $empresa->id,
            'validade'   => $validacao['validade'],
            // senha e conteúdo NUNCA são logados
        ]);

        return [
            'validade'         => $validacao['validade'],
            'dias_para_vencer' => $validacao['dias_para_vencer'],
            'razao_social'     => $validacao['razao_social'],
        ];
    }

    /**
     * Carrega o certificado descriptografado para uso na emissão.
     * Retorna o objeto Certificate pronto para assinar XMLs.
     *
     * Uso interno do SefazService — não expor via API.
     */
    public function carregar(Empresa $empresa): Certificate
    {
        if (! $empresa->certificado_path) {
            throw new \RuntimeException('Empresa não possui certificado configurado.');
        }

        $conteudoCriptografado = Storage::disk(config('fiscal.storage.disk', 's3'))
            ->get($empresa->certificado_path);

        if (! $conteudoCriptografado) {
            throw new \RuntimeException('Arquivo de certificado não encontrado no storage.');
        }

        $pfx = Crypt::decryptString($conteudoCriptografado);
        $senha = $empresa->certificado_senha; // cast 'encrypted' descriptografa

        return Certificate::readPfx($pfx, $senha);
    }

    /**
     * Verifica certificados próximos do vencimento (para alertas/notificações).
     *
     * @param int $diasAlerta Alertar com N dias de antecedência
     * @return array Lista de empresas com certificado vencendo
     */
    public function certificadosVencendo(int $diasAlerta = 30): array
    {
        return Empresa::whereNotNull('certificado_validade')
            ->where('certificado_validade', '<=', now()->addDays($diasAlerta))
            ->where('is_active', true)
            ->get(['id', 'razao_social', 'cnpj', 'certificado_validade'])
            ->map(fn ($e) => [
                'empresa_id'       => $e->id,
                'razao_social'     => $e->razao_social,
                'validade'         => $e->certificado_validade->format('d/m/Y'),
                'dias_para_vencer' => (int) now()->diffInDays($e->certificado_validade, false),
                'vencido'          => $e->certificado_validade->isPast(),
            ])
            ->toArray();
    }

    /**
     * Remove o certificado de uma empresa.
     */
    public function remover(Empresa $empresa): void
    {
        if ($empresa->certificado_path) {
            Storage::disk(config('fiscal.storage.disk', 's3'))->delete($empresa->certificado_path);
        }

        $empresa->update([
            'certificado_path'     => null,
            'certificado_senha'    => null,
            'certificado_validade' => null,
        ]);
    }

    // ─── Privados ─────────────────────────────────────────────────────────────

    private function extrairCnpj(Certificate $cert): ?string
    {
        // O CNPJ costuma estar no campo subject (CN) após "NOME:CNPJ"
        $subject = $cert->getSubjectName() ?? '';
        if (preg_match('/(\d{14})/', $subject, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extrairRazaoSocial(Certificate $cert): ?string
    {
        $subject = $cert->getSubjectName() ?? '';
        // Remove o sufixo :CNPJ do CN
        if (preg_match('/CN=([^:,]+)/', $subject, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
