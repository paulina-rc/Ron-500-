<?php

namespace Tests\Unit\Rumi;

use App\Domain\Rumi\Palo;

class CartaTest extends CasoDePruebaRumi
{
    public function test_valor_en_puntos_de_cada_numero(): void
    {
        $this->assertSame(15, $this->carta('A', Palo::CORAZONES)->puntos());
        $this->assertSame(20, $this->carta('2', Palo::CORAZONES)->puntos());
        foreach (['3', '4', '5', '6', '7', '8', '9'] as $numero) {
            $this->assertSame(5, $this->carta($numero, Palo::CORAZONES)->puntos());
        }
        foreach (['10', 'J', 'Q', 'K'] as $numero) {
            $this->assertSame(10, $this->carta($numero, Palo::CORAZONES)->puntos());
        }
    }

    public function test_dos_cartas_iguales_de_barajas_distintas_tienen_id_unico(): void
    {
        $siete1 = $this->carta('7', Palo::CORAZONES, 1);
        $siete2 = $this->carta('7', Palo::CORAZONES, 2);

        $this->assertNotSame($siete1->id, $siete2->id);
        $this->assertSame('7H#1', $siete1->id);
        $this->assertSame('7H#2', $siete2->id);
        $this->assertFalse($siete1->esIgualA($siete2));
    }

    public function test_el_as_tiene_dos_rangos_posibles_y_las_demas_uno_solo(): void
    {
        $this->assertSame([1, 14], $this->carta('A', Palo::PICAS)->rangosPosibles());
        $this->assertSame([7], $this->carta('7', Palo::PICAS)->rangosPosibles());
        $this->assertSame([13], $this->carta('K', Palo::PICAS)->rangosPosibles());
    }
}
