<?php

namespace Tests\Unit\Rumi;

use App\Domain\Rumi\CartaEnMesa;
use App\Domain\Rumi\JuegoMesa;
use App\Domain\Rumi\MotorRumi;
use App\Domain\Rumi\Palo;

class PuntuacionTest extends CasoDePruebaRumi
{
    public function test_puntos_de_ronda_suman_mesa_mas_gane_menos_mano(): void
    {
        $mesa = [
            'trio' => new JuegoMesa('trio', JuegoMesa::TRIO, [
                new CartaEnMesa($this->carta('A', Palo::CORAZONES), 'j1'), // 15
                new CartaEnMesa($this->carta('A', Palo::DIAMANTES), 'j1'), // 15
                new CartaEnMesa($this->carta('A', Palo::PICAS), 'j1'),     // 15
            ]),
        ];

        $estado = $this->estado(
            manoJ1: [],
            manoJ2: [$this->carta('K', Palo::CORAZONES), $this->carta('2', Palo::PICAS)], // 10 + 20
            mesa: $mesa,
        );
        $estado->ganadorRonda = 'j1';

        // j1: 45 en mesa + 20 de gane - 0 en mano
        $this->assertSame(65, MotorRumi::puntosDeRonda($estado, 'j1'));

        // j2: 0 en mesa + 0 de gane - 30 en mano
        $this->assertSame(-30, MotorRumi::puntosDeRonda($estado, 'j2'));
    }

    public function test_el_gane_solo_es_para_quien_cerro_la_ronda(): void
    {
        $estado = $this->estado(manoJ1: [], manoJ2: []);
        $estado->ganadorRonda = null; // se acabó el mazo, nadie salió

        $this->assertSame(0, MotorRumi::puntosDeRonda($estado, 'j1'));
        $this->assertSame(0, MotorRumi::puntosDeRonda($estado, 'j2'));
    }
}
