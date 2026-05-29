<?php

namespace App\Enums;

enum LancamentoStatus: string
{
    case ABERTO       = 'aberto';
    case PARCIAL      = 'parcial';
    case PAGO         = 'pago';
    case VENCIDO      = 'vencido';
    case CANCELADO    = 'cancelado';
    case RENEGOCIADO  = 'renegociado';

    public function label(): string
    {
        return match($this) {
            self::ABERTO      => 'Em aberto',
            self::PARCIAL     => 'Parcialmente pago',
            self::PAGO        => 'Pago',
            self::VENCIDO     => 'Vencido',
            self::CANCELADO   => 'Cancelado',
            self::RENEGOCIADO => 'Renegociado',
        };
    }

    public function cor(): string
    {
        return match($this) {
            self::ABERTO      => 'blue',
            self::PARCIAL     => 'yellow',
            self::PAGO        => 'green',
            self::VENCIDO     => 'red',
            self::CANCELADO   => 'gray',
            self::RENEGOCIADO => 'purple',
        };
    }

    public function podeReceberBaixa(): bool
    {
        return in_array($this, [self::ABERTO, self::PARCIAL, self::VENCIDO]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::PAGO, self::CANCELADO, self::RENEGOCIADO]);
    }
}
