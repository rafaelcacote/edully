<?php

namespace App\Enums;

enum TipoDocumento: string
{
    case Atestado = 'atestado';
    case PedidoDeclaracao = 'pedido_declaracao';
    case DocumentoEscola = 'documento_escola';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Tipos que o responsável pode criar pelo app.
     *
     * @return list<string>
     */
    public static function mobileCreatableValues(): array
    {
        return [
            self::Atestado->value,
            self::PedidoDeclaracao->value,
        ];
    }

    /**
     * Tipos enviados pelo responsável que a escola precisa analisar.
     *
     * @return list<string>
     */
    public static function schoolAttentionValues(): array
    {
        return [
            self::Atestado->value,
            self::PedidoDeclaracao->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Atestado => 'Atestado médico',
            self::PedidoDeclaracao => 'Pedido de declaração',
            self::DocumentoEscola => 'Documento da escola',
        };
    }
}
