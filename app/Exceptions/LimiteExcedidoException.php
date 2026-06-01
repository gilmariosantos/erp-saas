<?php
namespace App\Exceptions;

use Exception;

class LimiteExcedidoException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code'    => 'LIMITE_EXCEDIDO',
            'upgrade_url' => '/assinatura/upgrade',
        ], 402);
    }
}
