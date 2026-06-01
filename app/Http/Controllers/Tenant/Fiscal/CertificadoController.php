<?php
namespace App\Http\Controllers\Tenant\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\Fiscal\Certificado\CertificadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Certificado Digital
 *
 * Upload e gestão de certificado A1.
 * SEGURANÇA: o .pfx e a senha nunca são retornados em nenhuma resposta.
 */
class CertificadoController extends Controller
{
    public function __construct(private readonly CertificadoService $service) {}

    /**
     * Valida um certificado SEM salvar (preview antes de confirmar).
     */
    public function validar(Request $request): JsonResponse
    {
        $request->validate([
            'certificado' => ['required', 'file', 'max:10240'], // máx 10MB
            'senha'       => ['required', 'string'],
        ]);

        $conteudo = file_get_contents($request->file('certificado')->getRealPath());
        $resultado = $this->service->validar($conteudo, $request->string('senha'));

        // Remove qualquer dado sensível antes de retornar
        unset($resultado['erro_tecnico']);

        return response()->json($resultado);
    }

    /**
     * Salva o certificado da empresa de forma segura.
     */
    public function upload(Request $request): JsonResponse
    {
        $this->authorize('configuracoes.gerenciar');

        $request->validate([
            'empresa_id'  => ['required', 'exists:empresas,id'],
            'certificado' => ['required', 'file', 'max:10240'],
            'senha'       => ['required', 'string'],
        ]);

        $empresa = Empresa::findOrFail($request->integer('empresa_id'));
        $conteudo = file_get_contents($request->file('certificado')->getRealPath());

        try {
            $resultado = $this->service->salvar($empresa, $conteudo, $request->string('senha'));
            return response()->json([
                'message' => 'Certificado salvo com sucesso.',
                'data'    => $resultado,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Mostra informações do certificado atual (sem expor senha/conteúdo).
     */
    public function info(Empresa $empresa): JsonResponse
    {
        if (! $empresa->certificado_path) {
            return response()->json(['configurado' => false]);
        }

        return response()->json([
            'configurado'      => true,
            'validade'         => $empresa->certificado_validade?->format('d/m/Y'),
            'dias_para_vencer' => $empresa->certificado_validade
                ? (int) now()->diffInDays($empresa->certificado_validade, false) : null,
            'vencido'          => $empresa->certificadoVencido(),
        ]);
    }

    /**
     * Remove o certificado.
     */
    public function remover(Empresa $empresa): JsonResponse
    {
        $this->authorize('configuracoes.gerenciar');
        $this->service->remover($empresa);
        return response()->json(['message' => 'Certificado removido.']);
    }
}
