<?php

namespace App\Console\Commands\Simulacion;

use App\Domain\Rumi\EstadoPartida;

/** Se superó el tope de turnos dentro de una misma ronda: la ronda no cierra sola. */
final class LimiteDeTurnosExcedido extends \RuntimeException
{
    public function __construct(
        public readonly EstadoPartida $estado,
        public readonly int $numeroPartida,
        public readonly int $numeroRonda,
        public readonly int $semilla,
        public readonly int $turnos,
    ) {
        parent::__construct("Ronda {$numeroRonda} de la partida {$numeroPartida} superó {$turnos} turnos sin cerrar.");
    }
}
