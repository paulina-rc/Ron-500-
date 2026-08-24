<?php

namespace Tests\Unit\Rumi;

use App\Domain\Rumi\CartaEnMesa;
use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\Excepciones\JugadaIlegalException;
use App\Domain\Rumi\JuegoMesa;
use App\Domain\Rumi\MotorRumi;
use App\Domain\Rumi\Palo;

class MotorComerTest extends CasoDePruebaRumi
{
    public function test_comer_del_mazo_agrega_la_carta_a_la_mano_y_cambia_de_fase(): void
    {
        $cartaDelMazo = $this->carta('9', Palo::TREBOLES);
        $estado = $this->estado(
            manoJ1: [$this->carta('3', Palo::CORAZONES)],
            manoJ2: [],
            mazo: [$cartaDelMazo],
        );

        $nuevo = MotorRumi::comerDelMazo($estado, 'j1');

        $this->assertCount(2, $nuevo->jugadores['j1']->mano);
        $this->assertTrue($nuevo->jugadores['j1']->tieneCarta($cartaDelMazo->id));
        $this->assertSame(EstadoPartida::PUEDE_JUGAR, $nuevo->fase);
        $this->assertCount(0, $nuevo->mazo);

        // el estado original no se muta
        $this->assertCount(1, $estado->jugadores['j1']->mano);
        $this->assertCount(1, $estado->mazo);
    }

    public function test_no_se_puede_comer_dos_veces_en_el_mismo_turno(): void
    {
        $estado = $this->estado(
            manoJ1: [],
            manoJ2: [],
            mazo: [$this->carta('9', Palo::TREBOLES)],
            fase: EstadoPartida::PUEDE_JUGAR, // ya comió
        );

        $this->expectException(JugadaIlegalException::class);
        MotorRumi::comerDelMazo($estado, 'j1');
    }

    public function test_no_se_puede_comer_si_no_es_su_turno(): void
    {
        $estado = $this->estado(
            manoJ1: [],
            manoJ2: [],
            mazo: [$this->carta('9', Palo::TREBOLES)],
            turno: 'j2',
        );

        $this->expectException(JugadaIlegalException::class);
        MotorRumi::comerDelMazo($estado, 'j1');
    }

    public function test_comer_de_la_basura_arrastra_todas_las_cartas_de_encima(): void
    {
        // Basura, de más vieja a más nueva: 2H (fondo), 3H, 5H (arriba). Pide el 2H.
        $dos = $this->carta('2', Palo::CORAZONES);
        $tres = $this->carta('3', Palo::CORAZONES, 2);
        $cinco = $this->carta('5', Palo::PICAS);

        $estado = $this->estado(
            manoJ1: [$this->carta('2', Palo::DIAMANTES), $this->carta('2', Palo::PICAS)],
            manoJ2: [],
            basura: [$dos, $tres, $cinco],
        );

        $nuevo = MotorRumi::comerDeLaBasura($estado, 'j1', $dos->id);

        $this->assertCount(0, $nuevo->basura);
        $this->assertCount(5, $nuevo->jugadores['j1']->mano); // 2 originales + 2H + 3H + 5H
        $this->assertTrue($nuevo->jugadores['j1']->tieneCarta($tres->id));
        $this->assertTrue($nuevo->jugadores['j1']->tieneCarta($cinco->id));
        $this->assertSame($dos->id, $nuevo->cartaObligatoriaId);
    }

    public function test_comer_de_la_basura_sin_jugada_legal_para_la_carta_obligatoria_lanza_excepcion(): void
    {
        $dos = $this->carta('2', Palo::CORAZONES);

        $estado = $this->estado(
            manoJ1: [$this->carta('9', Palo::TREBOLES), $this->carta('K', Palo::PICAS)],
            manoJ2: [],
            basura: [$dos],
        );

        $this->expectException(JugadaIlegalException::class);
        MotorRumi::comerDeLaBasura($estado, 'j1', $dos->id);
    }

    public function test_comer_de_la_basura_es_legal_si_la_carta_obligatoria_puede_embonar(): void
    {
        $dosCorazones = $this->carta('2', Palo::CORAZONES);

        $mesa = [
            'j1trio' => new JuegoMesa('j1trio', JuegoMesa::TRIO, [
                new CartaEnMesa($this->carta('2', Palo::DIAMANTES), 'j1'),
                new CartaEnMesa($this->carta('2', Palo::PICAS), 'j1'),
                new CartaEnMesa($this->carta('2', Palo::TREBOLES), 'j1'),
            ]),
        ];

        $estado = $this->estado(
            manoJ1: [$this->carta('9', Palo::TREBOLES)],
            manoJ2: [],
            basura: [$dosCorazones],
            mesa: $mesa,
            j1Bajado: true,
        );

        $nuevo = MotorRumi::comerDeLaBasura($estado, 'j1', $dosCorazones->id);

        $this->assertSame($dosCorazones->id, $nuevo->cartaObligatoriaId);
        $this->assertTrue($nuevo->jugadores['j1']->tieneCarta($dosCorazones->id));
    }
}
