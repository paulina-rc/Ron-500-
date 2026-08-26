<?php

namespace Tests\Unit\Rumi;

use App\Domain\Rumi\CartaEnMesa;
use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\JuegoMesa;
use App\Domain\Rumi\Palo;

class SerializacionEstadoTest extends CasoDePruebaRumi
{
    public function test_serializar_y_reconstruir_da_un_estado_identico(): void
    {
        $mesa = [
            'j1' => new JuegoMesa('j1', JuegoMesa::TRIO, [
                new CartaEnMesa($this->carta('7', Palo::CORAZONES), 'j1'),
                new CartaEnMesa($this->carta('7', Palo::DIAMANTES), 'j1'),
                // embonada por j2: el dueño de esta carta es distinto del dueño del juego
                new CartaEnMesa($this->carta('7', Palo::PICAS), 'j2'),
            ]),
            'j2' => new JuegoMesa('j2', JuegoMesa::ESCALERA, [
                new CartaEnMesa($this->carta('4', Palo::TREBOLES), 'j2'),
                new CartaEnMesa($this->carta('5', Palo::TREBOLES), 'j2'),
                new CartaEnMesa($this->carta('6', Palo::TREBOLES), 'j1'),
            ]),
        ];

        $original = $this->estado(
            manoJ1: [
                $this->carta('A', Palo::CORAZONES, 1),
                $this->carta('A', Palo::CORAZONES, 2), // duplicado exacto de la otra baraja
                $this->carta('K', Palo::PICAS),
            ],
            manoJ2: [$this->carta('9', Palo::DIAMANTES)],
            basura: [$this->carta('2', Palo::PICAS), $this->carta('3', Palo::CORAZONES)],
            mazo: [$this->carta('10', Palo::TREBOLES), $this->carta('J', Palo::DIAMANTES)],
            mesa: $mesa,
            turno: 'j2',
            fase: EstadoPartida::PUEDE_JUGAR,
            cartaObligatoriaId: (string) $this->carta('9', Palo::DIAMANTES)->id,
            j1Bajado: true,
            j2Bajado: true,
        );
        $original->rondaTerminada = false;
        $original->ganadorRonda = null;

        $reconstruido = EstadoPartida::desdeArray($original->aArray());

        // comparación estructural completa: el ida y vuelta debe dar exactamente lo mismo
        $this->assertSame($original->aArray(), $reconstruido->aArray());

        // chequeos puntuales, sobre todo el dueño de cada carta en mesa (no el dueño del juego)
        $this->assertSame('j2', $reconstruido->mesa['j1']->cartas[2]->propietarioId);
        $this->assertSame('j1', $reconstruido->mesa['j2']->cartas[2]->propietarioId);
        $this->assertSame(Palo::PICAS, $reconstruido->mesa['j1']->cartas[2]->carta->palo);

        $this->assertTrue($reconstruido->jugadores['j1']->seHaBajado);
        $this->assertSame($original->cartaObligatoriaId, $reconstruido->cartaObligatoriaId);
        $this->assertSame($original->turnoActual, $reconstruido->turnoActual);
        $this->assertSame($original->fase, $reconstruido->fase);
        $this->assertCount(3, $reconstruido->jugadores['j1']->mano);
        $this->assertSame('AH#1', $reconstruido->jugadores['j1']->mano[0]->id);
        $this->assertSame('AH#2', $reconstruido->jugadores['j1']->mano[1]->id);
        $this->assertSame('KS#1', $reconstruido->jugadores['j1']->mano[2]->id);
    }

    public function test_el_orden_de_la_mano_y_de_la_basura_sobrevive_exacto_al_ida_y_vuelta(): void
    {
        // Orden a propósito NO ordenado ni por rango (K,3,A,7,A) ni alfabéticamente por id
        // (KS#1, 3H#1, AD#2, 7C#1, AD#1 tampoco queda alfabético), para que un bug que
        // reordene o reindexe por id no pueda pasar la prueba por casualidad.
        $manoDesordenada = [
            $this->carta('K', Palo::PICAS),
            $this->carta('3', Palo::CORAZONES),
            $this->carta('A', Palo::DIAMANTES, 2),
            $this->carta('7', Palo::TREBOLES),
            $this->carta('A', Palo::DIAMANTES, 1),
        ];
        $idsManoOriginal = array_map(fn ($c) => $c->id, $manoDesordenada);

        // La basura es escalonada: la posición de cada carta define qué se arrastra al comer,
        // así que su orden es tan crítico como el de la mano.
        $basuraDesordenada = [
            $this->carta('Q', Palo::PICAS),
            $this->carta('4', Palo::CORAZONES),
            $this->carta('J', Palo::DIAMANTES),
        ];
        $idsBasuraOriginal = array_map(fn ($c) => $c->id, $basuraDesordenada);

        $original = $this->estado(
            manoJ1: $manoDesordenada,
            manoJ2: [],
            basura: $basuraDesordenada,
        );

        $reconstruido = EstadoPartida::desdeArray($original->aArray());

        $idsManoReconstruida = array_map(fn ($c) => $c->id, $reconstruido->jugadores['j1']->mano);
        $this->assertSame($idsManoOriginal, $idsManoReconstruida);

        $idsBasuraReconstruida = array_map(fn ($c) => $c->id, $reconstruido->basura);
        $this->assertSame($idsBasuraOriginal, $idsBasuraReconstruida);

        // y también tras pasar por json_encode/json_decode real, como hace la columna de la tabla
        $datos = json_decode(json_encode($original->aArray()), true);
        $reconstruidoDesdeJson = EstadoPartida::desdeArray($datos);

        $this->assertSame(
            $idsManoOriginal,
            array_map(fn ($c) => $c->id, $reconstruidoDesdeJson->jugadores['j1']->mano)
        );
        $this->assertSame(
            $idsBasuraOriginal,
            array_map(fn ($c) => $c->id, $reconstruidoDesdeJson->basura)
        );
    }

    public function test_el_ida_y_vuelta_sobrevive_json_encode_decode_como_lo_hace_la_base_de_datos(): void
    {
        $original = EstadoPartida::iniciar(['j1', 'j2'], semilla: 42);

        // simula exactamente lo que hace la columna JSON de la tabla partidas
        $json = json_encode($original->aArray());
        $datos = json_decode($json, true);
        $reconstruido = EstadoPartida::desdeArray($datos);

        $this->assertSame($original->aArray(), $reconstruido->aArray());
    }
}
