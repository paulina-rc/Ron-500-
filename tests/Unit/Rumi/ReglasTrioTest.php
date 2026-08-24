<?php

namespace Tests\Unit\Rumi;

use App\Domain\Rumi\Palo;
use App\Domain\Rumi\Reglas;

class ReglasTrioTest extends CasoDePruebaRumi
{
    public function test_trio_de_tres_palos_distintos_es_valido(): void
    {
        $cartas = [
            $this->carta('7', Palo::CORAZONES),
            $this->carta('7', Palo::DIAMANTES),
            $this->carta('7', Palo::PICAS),
        ];

        $this->assertTrue(Reglas::esTrioValido($cartas));
    }

    public function test_trio_de_los_cuatro_palos_es_valido(): void
    {
        $cartas = [
            $this->carta('K', Palo::CORAZONES),
            $this->carta('K', Palo::DIAMANTES),
            $this->carta('K', Palo::TREBOLES),
            $this->carta('K', Palo::PICAS),
        ];

        $this->assertTrue(Reglas::esTrioValido($cartas));
    }

    public function test_trio_con_menos_de_tres_cartas_no_es_valido(): void
    {
        $cartas = [
            $this->carta('7', Palo::CORAZONES),
            $this->carta('7', Palo::DIAMANTES),
        ];

        $this->assertFalse(Reglas::esTrioValido($cartas));
    }

    public function test_trio_con_numeros_distintos_no_es_valido(): void
    {
        $cartas = [
            $this->carta('7', Palo::CORAZONES),
            $this->carta('7', Palo::DIAMANTES),
            $this->carta('8', Palo::PICAS),
        ];

        $this->assertFalse(Reglas::esTrioValido($cartas));
    }

    public function test_trio_con_palo_repetido_no_es_valido_aunque_venga_de_la_otra_baraja(): void
    {
        // 7H de la baraja 1 y 7H de la baraja 2: mismo palo, no se puede repetir dentro del trío.
        $cartas = [
            $this->carta('7', Palo::CORAZONES, 1),
            $this->carta('7', Palo::CORAZONES, 2),
            $this->carta('7', Palo::PICAS),
        ];

        $this->assertFalse(Reglas::esTrioValido($cartas));
    }
}
