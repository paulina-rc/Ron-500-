<?php

namespace App\Console\Commands\Simulacion;

/** Se superó el tope de rondas dentro de una misma partida: nadie llega a 500. */
final class LimiteDeRondasExcedido extends \RuntimeException
{
    /** @param array<string, int> $puntajes */
    public function __construct(
        public readonly array $puntajes,
        public readonly int $rondasJugadas,
        public readonly int $numeroPartida,
        public readonly int $semilla,
    ) {
        parent::__construct("Partida {$numeroPartida} superó {$rondasJugadas} rondas sin que nadie llegara a 500.");
    }
}
