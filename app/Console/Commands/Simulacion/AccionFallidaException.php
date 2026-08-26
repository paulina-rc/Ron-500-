<?php

namespace App\Console\Commands\Simulacion;

use App\Domain\Rumi\EstadoPartida;
use Throwable;

/**
 * Envuelve cualquier excepción lanzada durante una acción del bot, junto con
 * el estado justo antes de intentarla, para poder reportar dónde reventó.
 */
final class AccionFallidaException extends \RuntimeException
{
    public function __construct(
        public readonly EstadoPartida $estadoAntes,
        public readonly string $jugadorId,
        public readonly string $accion,
        Throwable $previa,
    ) {
        parent::__construct($previa->getMessage(), 0, $previa);
    }
}
