<?php

namespace Tests\Unit\Rumi;

use App\Domain\Rumi\Reglas;
use PHPUnit\Framework\TestCase;

class GanadorPartidaTest extends TestCase
{
    public function test_nadie_gana_si_nadie_llega_a_500(): void
    {
        $this->assertNull(Reglas::ganadorDeLaPartida(['j1' => 499, 'j2' => 320]));
    }

    public function test_gana_el_que_llega_justo_a_500(): void
    {
        $this->assertSame('j1', Reglas::ganadorDeLaPartida(['j1' => 500, 'j2' => 480]));
    }

    public function test_si_ambos_pasan_de_500_gana_el_que_tiene_mas(): void
    {
        $this->assertSame('j2', Reglas::ganadorDeLaPartida(['j1' => 510, 'j2' => 545]));
    }

    public function test_empate_exacto_arriba_de_500_no_define_ganador(): void
    {
        $this->assertNull(Reglas::ganadorDeLaPartida(['j1' => 520, 'j2' => 520]));
    }

    public function test_los_puntajes_negativos_no_rompen_nada(): void
    {
        $this->assertNull(Reglas::ganadorDeLaPartida(['j1' => -40, 'j2' => -10]));
    }

    public function test_ids_numericos_vuelven_como_string(): void
    {
        $this->assertSame('7', Reglas::ganadorDeLaPartida([7 => 600, 8 => 100]));
    }
}
