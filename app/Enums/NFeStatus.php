<?php

namespace App\Enums;

/**
 * Status do ciclo de vida de uma NF-e.
 */
enum NFeStatus: string
{
    case RASCUNHO     = 'rascunho';
    case PENDENTE     = 'pendente';
    case PROCESSANDO  = 'processando';
    case AUTORIZADA   = 'autorizada';
    case CANCELADA    = 'cancelada';
    case DENEGADA     = 'denegada';
    case REJEITADA    = 'rejeitada';
    case CONTINGENCIA = 'contingencia';
    case INUTILIZADA  = 'inutilizada';

    public function label(): string
    {
        return match($this) {
            self::RASCUNHO     => 'Rascunho',
            self::PENDENTE     => 'Pendente',
            self::PROCESSANDO  => 'Processando',
            self::AUTORIZADA   => 'Autorizada',
            self::CANCELADA    => 'Cancelada',
            self::DENEGADA     => 'Denegada',
            self::REJEITADA    => 'Rejeitada',
            self::CONTINGENCIA => 'Contingência',
            self::INUTILIZADA  => 'Inutilizada',
        };
    }

    public function cor(): string
    {
        return match($this) {
            self::RASCUNHO     => 'gray',
            self::PENDENTE     => 'yellow',
            self::PROCESSANDO  => 'blue',
            self::AUTORIZADA   => 'green',
            self::CANCELADA    => 'red',
            self::DENEGADA     => 'red',
            self::REJEITADA    => 'orange',
            self::CONTINGENCIA => 'purple',
            self::INUTILIZADA  => 'gray',
        };
    }

    public function podeEmitir(): bool
    {
        return in_array($this, [self::RASCUNHO, self::REJEITADA]);
    }

    public function podeCancelar(): bool
    {
        return $this === self::AUTORIZADA;
    }

    public function podeCce(): bool
    {
        return $this === self::AUTORIZADA;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::CANCELADA, self::DENEGADA, self::INUTILIZADA]);
    }
}
