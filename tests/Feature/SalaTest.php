<?php

namespace Tests\Feature;

use App\Models\Sala;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_sala_genera_codigo_unico_de_cuatro_letras(): void
    {
        $codigos = [];
        for ($i = 0; $i < 20; $i++) {
            $sala = Sala::crear();

            $this->assertMatchesRegularExpression('/^[A-Z]{4}$/', $sala->codigo);
            $this->assertSame(Sala::ESPERANDO, $sala->estado);
            $this->assertDatabaseHas('salas', ['id' => $sala->id, 'codigo' => $sala->codigo]);

            $codigos[] = $sala->codigo;
        }

        $this->assertCount(20, array_unique($codigos));
    }
}
