<?php

namespace Tests\Unit\Rumi;

use App\Domain\Rumi\Palo;
use App\Domain\Rumi\Reglas;

class ReglasEscaleraTest extends CasoDePruebaRumi
{
    public function test_escalera_con_as_como_uno_es_valida(): void
    {
        $cartas = [
            $this->carta('A', Palo::CORAZONES),
            $this->carta('2', Palo::CORAZONES),
            $this->carta('3', Palo::CORAZONES),
        ];

        $this->assertTrue(Reglas::esEscaleraValida($cartas));
        $this->assertSame([1, 3], Reglas::resolverRangoEscalera($cartas));
    }

    public function test_escalera_con_as_como_catorce_es_valida(): void
    {
        $cartas = [
            $this->carta('Q', Palo::PICAS),
            $this->carta('K', Palo::PICAS),
            $this->carta('A', Palo::PICAS),
        ];

        $this->assertTrue(Reglas::esEscaleraValida($cartas));
        $this->assertSame([12, 14], Reglas::resolverRangoEscalera($cartas));
    }

    public function test_escalera_no_da_la_vuelta_k_a_2_no_es_valida(): void
    {
        $cartas = [
            $this->carta('K', Palo::PICAS),
            $this->carta('A', Palo::PICAS),
            $this->carta('2', Palo::PICAS),
        ];

        $this->assertFalse(Reglas::esEscaleraValida($cartas));
        $this->assertNull(Reglas::resolverRangoEscalera($cartas));
    }

    public function test_escalera_de_palos_distintos_no_es_valida(): void
    {
        $cartas = [
            $this->carta('4', Palo::CORAZONES),
            $this->carta('5', Palo::CORAZONES),
            $this->carta('6', Palo::PICAS),
        ];

        $this->assertFalse(Reglas::esEscaleraValida($cartas));
    }

    public function test_escalera_con_numero_repetido_no_es_valida(): void
    {
        $cartas = [
            $this->carta('4', Palo::CORAZONES, 1),
            $this->carta('4', Palo::CORAZONES, 2),
            $this->carta('5', Palo::CORAZONES),
        ];

        $this->assertFalse(Reglas::esEscaleraValida($cartas));
    }

    public function test_escalera_con_menos_de_tres_cartas_no_es_valida(): void
    {
        $cartas = [
            $this->carta('4', Palo::CORAZONES),
            $this->carta('5', Palo::CORAZONES),
        ];

        $this->assertFalse(Reglas::esEscaleraValida($cartas));
    }

    public function test_escalera_con_salto_no_es_valida(): void
    {
        $cartas = [
            $this->carta('4', Palo::CORAZONES),
            $this->carta('5', Palo::CORAZONES),
            $this->carta('7', Palo::CORAZONES),
        ];

        $this->assertFalse(Reglas::esEscaleraValida($cartas));
    }
}
