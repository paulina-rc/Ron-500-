<?php

namespace Tests\Unit\Rumi;

use App\Domain\Rumi\CartaEnMesa;
use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\Excepciones\JugadaIlegalException;
use App\Domain\Rumi\JuegoMesa;
use App\Domain\Rumi\MotorRumi;
use App\Domain\Rumi\Palo;

class MotorBajarEmbonarTest extends CasoDePruebaRumi
{
    public function test_bajar_crea_un_juego_en_mesa_y_marca_que_el_jugador_ya_se_bajo(): void
    {
        $siete1 = $this->carta('7', Palo::CORAZONES);
        $siete2 = $this->carta('7', Palo::DIAMANTES);
        $siete3 = $this->carta('7', Palo::PICAS);
        $otra = $this->carta('9', Palo::TREBOLES);

        $estado = $this->estado(
            manoJ1: [$siete1, $siete2, $siete3, $otra],
            manoJ2: [],
            fase: EstadoPartida::PUEDE_JUGAR,
        );

        $nuevo = MotorRumi::bajar($estado, 'j1', [$siete1->id, $siete2->id, $siete3->id]);

        $this->assertCount(1, $nuevo->mesa);
        $juego = array_values($nuevo->mesa)[0];
        $this->assertSame(JuegoMesa::TRIO, $juego->tipo);
        $this->assertCount(3, $juego->cartas);
        $this->assertTrue($nuevo->jugadores['j1']->seHaBajado);
        $this->assertCount(1, $nuevo->jugadores['j1']->mano);
        $this->assertTrue($nuevo->jugadores['j1']->tieneCarta($otra->id));

        // el estado original no se muta
        $this->assertFalse($estado->jugadores['j1']->seHaBajado);
    }

    public function test_no_se_puede_embonar_sin_haberse_bajado_antes(): void
    {
        $mesa = [
            'ajeno' => new JuegoMesa('ajeno', JuegoMesa::TRIO, [
                new CartaEnMesa($this->carta('7', Palo::CORAZONES), 'j2'),
                new CartaEnMesa($this->carta('7', Palo::DIAMANTES), 'j2'),
                new CartaEnMesa($this->carta('7', Palo::PICAS), 'j2'),
            ]),
        ];

        $sieteClub = $this->carta('7', Palo::TREBOLES);
        $estado = $this->estado(
            manoJ1: [$sieteClub],
            manoJ2: [],
            mesa: $mesa,
            fase: EstadoPartida::PUEDE_JUGAR,
            j1Bajado: false,
        );

        $this->expectException(JugadaIlegalException::class);
        MotorRumi::embonar($estado, 'j1', 'ajeno', $sieteClub->id);
    }

    public function test_los_puntos_del_embone_son_de_quien_lo_puso_no_del_dueno_del_juego(): void
    {
        $mesa = [
            'j1trio' => new JuegoMesa('j1trio', JuegoMesa::TRIO, [
                new CartaEnMesa($this->carta('7', Palo::CORAZONES), 'j1'),
                new CartaEnMesa($this->carta('7', Palo::DIAMANTES), 'j1'),
                new CartaEnMesa($this->carta('7', Palo::PICAS), 'j1'),
            ]),
        ];

        $sieteClub = $this->carta('7', Palo::TREBOLES);
        $otra = $this->carta('9', Palo::PICAS);
        $estado = $this->estado(
            manoJ1: [],
            manoJ2: [$sieteClub, $otra],
            mesa: $mesa,
            turno: 'j2',
            fase: EstadoPartida::PUEDE_JUGAR,
            j2Bajado: true,
        );

        $nuevo = MotorRumi::embonar($estado, 'j2', 'j1trio', $sieteClub->id);

        $juego = $nuevo->mesa['j1trio'];
        $this->assertCount(4, $juego->cartas);

        $embonada = array_values(array_filter(
            $juego->cartas,
            fn (CartaEnMesa $c) => $c->carta->id === $sieteClub->id
        ))[0];
        $this->assertSame('j2', $embonada->propietarioId);

        // j1 puso 3 sietes (15 pts), j2 embonó 1 siete (5 pts) pero le quedó el 9♠ (5 pts) en mano: cada quien se queda con lo suyo.
        $this->assertSame(15, MotorRumi::puntosDeRonda($nuevo, 'j1'));
        $this->assertSame(0, MotorRumi::puntosDeRonda($nuevo, 'j2'));
    }

    public function test_una_escalera_se_puede_extender_por_abajo_y_por_arriba(): void
    {
        $mesa = [
            'esc' => new JuegoMesa('esc', JuegoMesa::ESCALERA, [
                new CartaEnMesa($this->carta('4', Palo::CORAZONES), 'j1'),
                new CartaEnMesa($this->carta('5', Palo::CORAZONES), 'j1'),
                new CartaEnMesa($this->carta('6', Palo::CORAZONES), 'j1'),
            ]),
        ];

        $tres = $this->carta('3', Palo::CORAZONES);
        $siete = $this->carta('7', Palo::CORAZONES);
        $otra = $this->carta('9', Palo::PICAS);

        $estado = $this->estado(
            manoJ1: [$tres, $siete, $otra],
            manoJ2: [],
            mesa: $mesa,
            fase: EstadoPartida::PUEDE_JUGAR,
            j1Bajado: true,
        );

        $conElTres = MotorRumi::embonar($estado, 'j1', 'esc', $tres->id);
        $this->assertSame([3, 6], $conElTres->mesa['esc']->rangoEscalera());

        $conElSiete = MotorRumi::embonar($conElTres, 'j1', 'esc', $siete->id);
        $this->assertSame([3, 7], $conElSiete->mesa['esc']->rangoEscalera());
        $this->assertCount(5, $conElSiete->mesa['esc']->cartas);
    }
}
