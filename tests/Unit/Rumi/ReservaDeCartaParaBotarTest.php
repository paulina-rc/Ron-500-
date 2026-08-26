<?php

namespace Tests\Unit\Rumi;

use App\Domain\Rumi\CartaEnMesa;
use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\Excepciones\JugadaIlegalException;
use App\Domain\Rumi\JuegoMesa;
use App\Domain\Rumi\MotorRumi;
use App\Domain\Rumi\Palo;

class ReservaDeCartaParaBotarTest extends CasoDePruebaRumi
{
    public function test_bajar_una_jugada_que_dejaria_la_mano_en_cero_lanza_excepcion(): void
    {
        $siete1 = $this->carta('7', Palo::CORAZONES);
        $siete2 = $this->carta('7', Palo::DIAMANTES);
        $siete3 = $this->carta('7', Palo::PICAS);

        $estado = $this->estado(
            manoJ1: [$siete1, $siete2, $siete3], // exactamente las 3 que bajaría: no quedaría nada para botar
            manoJ2: [],
            fase: EstadoPartida::PUEDE_JUGAR,
        );

        $this->expectException(JugadaIlegalException::class);
        $this->expectExceptionMessage(
            'No podés bajar esas cartas: te quedarías sin nada que botar, y hay que botar siempre para cerrar el turno.'
        );

        MotorRumi::bajar($estado, 'j1', [$siete1->id, $siete2->id, $siete3->id]);
    }

    public function test_embonar_la_ultima_carta_de_la_mano_lanza_excepcion(): void
    {
        $mesa = [
            'j1trio' => new JuegoMesa('j1trio', JuegoMesa::TRIO, [
                new CartaEnMesa($this->carta('7', Palo::CORAZONES), 'j1'),
                new CartaEnMesa($this->carta('7', Palo::DIAMANTES), 'j1'),
                new CartaEnMesa($this->carta('7', Palo::PICAS), 'j1'),
            ]),
        ];

        $ultimaCarta = $this->carta('7', Palo::TREBOLES);
        $estado = $this->estado(
            manoJ1: [$ultimaCarta], // es la única carta que le queda
            manoJ2: [],
            mesa: $mesa,
            fase: EstadoPartida::PUEDE_JUGAR,
            j1Bajado: true,
        );

        $this->expectException(JugadaIlegalException::class);
        $this->expectExceptionMessage(
            'No podés embonar esa carta: te quedarías sin nada que botar, y hay que botar siempre para cerrar el turno.'
        );

        MotorRumi::embonar($estado, 'j1', 'j1trio', $ultimaCarta->id);
    }

    public function test_bajar_dejando_exactamente_una_carta_funciona_y_esa_carta_permite_salir_con_gane(): void
    {
        $siete1 = $this->carta('7', Palo::CORAZONES);
        $siete2 = $this->carta('7', Palo::DIAMANTES);
        $siete3 = $this->carta('7', Palo::PICAS);
        $ultimaCarta = $this->carta('9', Palo::TREBOLES);

        $estado = $this->estado(
            manoJ1: [$siete1, $siete2, $siete3, $ultimaCarta],
            manoJ2: [$this->carta('K', Palo::CORAZONES)],
            mazo: [$this->carta('4', Palo::DIAMANTES)],
            fase: EstadoPartida::PUEDE_JUGAR,
        );

        $despuesDeBajar = MotorRumi::bajar($estado, 'j1', [$siete1->id, $siete2->id, $siete3->id]);

        $this->assertCount(1, $despuesDeBajar->jugadores['j1']->mano);
        $this->assertTrue($despuesDeBajar->jugadores['j1']->tieneCarta($ultimaCarta->id));
        $this->assertTrue($despuesDeBajar->jugadores['j1']->seHaBajado);

        $despuesDeBotar = MotorRumi::botar($despuesDeBajar, 'j1', $ultimaCarta->id);

        $this->assertCount(0, $despuesDeBotar->jugadores['j1']->mano);
        $this->assertTrue($despuesDeBotar->rondaTerminada);
        $this->assertSame('j1', $despuesDeBotar->ganadorRonda);
    }
}
