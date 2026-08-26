<?php

namespace Tests\Unit\Rumi;

use App\Domain\Rumi\CartaEnMesa;
use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\Excepciones\JugadaIlegalException;
use App\Domain\Rumi\Jugador;
use App\Domain\Rumi\JuegoMesa;
use App\Domain\Rumi\MotorRumi;
use App\Domain\Rumi\Palo;

/**
 * PHP convierte automáticamente cualquier clave de array que "parezca" un entero
 * decimal (p. ej. "1") a int. Como el motor indexa a los jugadores por su id,
 * usar ids numéricos (típico: ids autoincrement de una base de datos) rompía
 * comparaciones estrictas en varios puntos. Estas pruebas usan ids "1"/"2" y
 * 1/2 a propósito para que revienten si ese bug vuelve.
 */
class IdsNumericosDeJugadorTest extends CasoDePruebaRumi
{
    public function test_ids_jugadores_devuelve_strings_reales_con_ids_numericos(): void
    {
        $estado = EstadoPartida::iniciar(['1', '2'], semilla: 1);

        $this->assertSame(['1', '2'], $estado->idsJugadores());
    }

    public function test_siguiente_jugador_alterna_correctamente_con_ids_string_numericos(): void
    {
        $estado = EstadoPartida::iniciar(['1', '2'], semilla: 1);

        $this->assertSame('2', $estado->siguienteJugador('1'));
        $this->assertSame('1', $estado->siguienteJugador('2'));
    }

    public function test_siguiente_jugador_alterna_correctamente_con_ids_enteros(): void
    {
        // El motor no exige que los ids lleguen como string: si llegan como int,
        // los parámetros tipados los coercionan, y debe funcionar igual.
        $estado = EstadoPartida::iniciar([1, 2], semilla: 1);

        $this->assertSame('2', $estado->siguienteJugador(1));
        $this->assertSame('1', $estado->siguienteJugador(2));
    }

    public function test_un_turno_completo_avanza_al_otro_jugador_con_ids_numericos(): void
    {
        $estado = EstadoPartida::iniciar(['1', '2'], semilla: 7);
        $turnoInicial = $estado->turnoActual;

        $trasComer = MotorRumi::comerDelMazo($estado, $turnoInicial);
        $primeraCarta = $trasComer->jugadores[$turnoInicial]->mano[0];
        $nuevo = MotorRumi::botar($trasComer, $turnoInicial, $primeraCarta->id);

        $this->assertNotSame($turnoInicial, $nuevo->turnoActual, 'el turno debe haber pasado al otro jugador');
        $this->assertContains($nuevo->turnoActual, ['1', '2']);
    }

    public function test_validar_turno_rechaza_al_jugador_equivocado_con_ids_numericos(): void
    {
        $estado = EstadoPartida::iniciar(['1', '2'], semilla: 3);
        $otro = $estado->turnoActual === '1' ? '2' : '1';

        $this->expectException(JugadaIlegalException::class);
        $this->expectExceptionMessage('No es tu turno.');

        MotorRumi::comerDelMazo($estado, $otro);
    }

    public function test_validar_turno_acepta_al_jugador_correcto_con_ids_numericos(): void
    {
        $estado = EstadoPartida::iniciar(['1', '2'], semilla: 3);

        $nuevo = MotorRumi::comerDelMazo($estado, $estado->turnoActual);

        $this->assertSame(EstadoPartida::PUEDE_JUGAR, $nuevo->fase);
    }

    public function test_puntos_de_ronda_calcula_correctamente_con_ids_numericos(): void
    {
        $mesa = [
            'trio' => new JuegoMesa('trio', JuegoMesa::TRIO, [
                new CartaEnMesa($this->carta('7', Palo::CORAZONES), '1'),
                new CartaEnMesa($this->carta('7', Palo::DIAMANTES), '1'),
                new CartaEnMesa($this->carta('7', Palo::PICAS), '2'), // embonada por el jugador "2"
            ]),
        ];

        $estado = new EstadoPartida(
            jugadores: [
                '1' => new Jugador('1', []),
                '2' => new Jugador('2', [$this->carta('9', Palo::TREBOLES)]),
            ],
            mazo: [],
            basura: [],
            mesa: $mesa,
            turnoActual: '1',
        );
        $estado->ganadorRonda = '1';

        // jugador "1": 10 pts en mesa (dos 7's) + 20 de gane - 0 en mano
        $this->assertSame(30, MotorRumi::puntosDeRonda($estado, '1'));
        // jugador "2": 5 pts en mesa (el 7 embonado) + 0 de gane - 5 en mano (el 9)
        $this->assertSame(0, MotorRumi::puntosDeRonda($estado, '2'));
    }
}
