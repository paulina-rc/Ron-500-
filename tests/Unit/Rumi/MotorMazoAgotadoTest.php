<?php

namespace Tests\Unit\Rumi;

use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\MotorRumi;
use App\Domain\Rumi\Palo;

class MotorMazoAgotadoTest extends CasoDePruebaRumi
{
    public function test_con_el_mazo_vacio_pero_una_carta_usable_en_la_basura_el_jugador_sigue_jugando(): void
    {
        $dos = $this->carta('2', Palo::CORAZONES);
        $nueve = $this->carta('9', Palo::TREBOLES);

        $estado = $this->estado(
            manoJ1: [$nueve, $this->carta('8', Palo::PICAS)],
            manoJ2: [$this->carta('2', Palo::DIAMANTES), $this->carta('2', Palo::PICAS)],
            basura: [$dos], // j2 puede armar un trío de 2's con el 2H de la basura
            mazo: [], // sin mazo
            fase: EstadoPartida::PUEDE_JUGAR,
        );

        $nuevo = MotorRumi::botar($estado, 'j1', $nueve->id);

        $this->assertFalse($nuevo->rondaTerminada);
        $this->assertNull($nuevo->ganadorRonda);
        $this->assertSame('j2', $nuevo->turnoActual);
        $this->assertSame(EstadoPartida::DEBE_COMER, $nuevo->fase);
        $this->assertTrue(MotorRumi::jugadorPuedeComer($nuevo, 'j2'));

        // y en efecto puede comerla, aunque no haya mazo
        $comida = MotorRumi::comerDeLaBasura($nuevo, 'j2', $dos->id);
        $this->assertCount(0, $comida->basura);
        $this->assertTrue($comida->jugadores['j2']->tieneCarta($dos->id));
    }

    public function test_con_el_mazo_vacio_y_nada_usable_en_la_basura_la_ronda_termina_sin_gane(): void
    {
        $dos = $this->carta('2', Palo::CORAZONES);
        $cinco = $this->carta('5', Palo::DIAMANTES);

        $estado = $this->estado(
            manoJ1: [$cinco, $this->carta('9', Palo::DIAMANTES)],
            // ninguna de estas dos cartas arma nada con el 2H ni con el 5D de la basura
            manoJ2: [$this->carta('9', Palo::PICAS), $this->carta('K', Palo::CORAZONES)],
            basura: [$dos],
            mazo: [],
            fase: EstadoPartida::PUEDE_JUGAR,
        );

        $nuevo = MotorRumi::botar($estado, 'j1', $cinco->id);

        $this->assertFalse(MotorRumi::jugadorPuedeComer($nuevo, 'j2'));
        $this->assertTrue($nuevo->rondaTerminada);
        $this->assertNull($nuevo->ganadorRonda);
    }

    public function test_alguien_que_sale_normalmente_con_el_mazo_ya_vacio_si_recibe_el_gane(): void
    {
        $ultima = $this->carta('9', Palo::TREBOLES);

        $estado = $this->estado(
            manoJ1: [$ultima],
            manoJ2: [$this->carta('K', Palo::CORAZONES)],
            basura: [$this->carta('4', Palo::PICAS)],
            mazo: [], // el mazo ya se acabó, pero igual puede salir botando su última carta
            fase: EstadoPartida::PUEDE_JUGAR,
        );

        $nuevo = MotorRumi::botar($estado, 'j1', $ultima->id);

        $this->assertTrue($nuevo->rondaTerminada);
        $this->assertSame('j1', $nuevo->ganadorRonda);
    }
}
