<?php

namespace Tests\Unit\Rumi;

use App\Domain\Rumi\Carta;
use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\Jugador;
use PHPUnit\Framework\TestCase;

abstract class CasoDePruebaRumi extends TestCase
{
    protected function carta(string $numero, string $palo, int $copia = 1): Carta
    {
        return new Carta($numero, $palo, $copia);
    }

    /**
     * @param Carta[] $manoJ1
     * @param Carta[] $manoJ2
     * @param Carta[] $basura
     * @param Carta[] $mazo
     * @param array<string, \App\Domain\Rumi\JuegoMesa> $mesa
     */
    protected function estado(
        array $manoJ1,
        array $manoJ2,
        array $basura = [],
        array $mazo = [],
        array $mesa = [],
        string $turno = 'j1',
        string $fase = EstadoPartida::DEBE_COMER,
        ?string $cartaObligatoriaId = null,
        bool $j1Bajado = false,
        bool $j2Bajado = false,
    ): EstadoPartida {
        return new EstadoPartida(
            jugadores: [
                'j1' => new Jugador('j1', $manoJ1, $j1Bajado),
                'j2' => new Jugador('j2', $manoJ2, $j2Bajado),
            ],
            mazo: $mazo,
            basura: $basura,
            mesa: $mesa,
            turnoActual: $turno,
            fase: $fase,
            cartaObligatoriaId: $cartaObligatoriaId,
        );
    }
}
