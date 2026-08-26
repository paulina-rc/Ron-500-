<?php

namespace Tests\Unit\Rumi;

use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\Excepciones\JugadaIlegalException;
use App\Domain\Rumi\MotorRumi;
use App\Domain\Rumi\Palo;

class MotorBotarTest extends CasoDePruebaRumi
{
    public function test_bajar_no_cierra_la_ronda_hay_que_botar_ademas(): void
    {
        $siete1 = $this->carta('7', Palo::CORAZONES);
        $siete2 = $this->carta('7', Palo::DIAMANTES);
        $siete3 = $this->carta('7', Palo::PICAS);
        $ultima = $this->carta('9', Palo::TREBOLES);

        $estado = $this->estado(
            manoJ1: [$siete1, $siete2, $siete3, $ultima],
            manoJ2: [],
            fase: EstadoPartida::PUEDE_JUGAR,
        );

        $nuevo = MotorRumi::bajar($estado, 'j1', [$siete1->id, $siete2->id, $siete3->id]);

        $this->assertCount(1, $nuevo->jugadores['j1']->mano);
        $this->assertFalse($nuevo->rondaTerminada);
        $this->assertNull($nuevo->ganadorRonda);
        $this->assertSame('j1', $nuevo->turnoActual, 'sigue siendo su turno: falta botar');
    }

    public function test_botar_la_ultima_carta_de_la_mano_termina_la_ronda_y_da_el_gane(): void
    {
        $ultima = $this->carta('9', Palo::TREBOLES);
        $estado = $this->estado(
            manoJ1: [$ultima],
            manoJ2: [$this->carta('K', Palo::CORAZONES)],
            fase: EstadoPartida::PUEDE_JUGAR,
        );

        $nuevo = MotorRumi::botar($estado, 'j1', $ultima->id);

        $this->assertTrue($nuevo->rondaTerminada);
        $this->assertSame('j1', $nuevo->ganadorRonda);
        $this->assertCount(1, $nuevo->basura);
    }

    public function test_botar_pasa_el_turno_al_siguiente_jugador_si_no_salio(): void
    {
        $estado = $this->estado(
            manoJ1: [$this->carta('9', Palo::TREBOLES), $this->carta('8', Palo::PICAS)],
            manoJ2: [],
            mazo: [$this->carta('4', Palo::DIAMANTES)], // mazo con cartas: no debe cerrar la ronda
            fase: EstadoPartida::PUEDE_JUGAR,
        );

        $nuevo = MotorRumi::botar($estado, 'j1', $estado->jugadores['j1']->mano[0]->id);

        $this->assertFalse($nuevo->rondaTerminada);
        $this->assertSame('j2', $nuevo->turnoActual);
        $this->assertSame(EstadoPartida::DEBE_COMER, $nuevo->fase);
    }

    public function test_no_se_puede_botar_la_carta_obligatoria_comida_de_la_basura(): void
    {
        $obligatoria = $this->carta('2', Palo::CORAZONES);
        $estado = $this->estado(
            manoJ1: [$obligatoria, $this->carta('9', Palo::TREBOLES)],
            manoJ2: [],
            fase: EstadoPartida::PUEDE_JUGAR,
            cartaObligatoriaId: $obligatoria->id,
        );

        $this->expectException(JugadaIlegalException::class);
        MotorRumi::botar($estado, 'j1', $obligatoria->id);
    }

    public function test_no_se_puede_botar_otra_carta_dejando_la_obligatoria_en_la_mano(): void
    {
        $obligatoria = $this->carta('2', Palo::CORAZONES);
        $otra = $this->carta('9', Palo::TREBOLES);
        $estado = $this->estado(
            manoJ1: [$obligatoria, $otra],
            manoJ2: [],
            fase: EstadoPartida::PUEDE_JUGAR,
            cartaObligatoriaId: $obligatoria->id,
        );

        $this->expectException(JugadaIlegalException::class);
        MotorRumi::botar($estado, 'j1', $otra->id);
    }
}
