<?php

namespace App\Enums;

enum CTeStatus: string
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
            self::AUTORIZADA   => 'Autorizado',
            self::CANCELADA    => 'Cancelado',
            self::DENEGADA     => 'Denegado',
            self::REJEITADA    => 'Rejeitado',
            self::CONTINGENCIA => 'Contingência',
            self::INUTILIZADA  => 'Inutilizado',
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
}
