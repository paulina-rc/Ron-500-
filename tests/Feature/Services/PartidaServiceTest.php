<?php

namespace Tests\Feature\Services;

use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\MotorRumi;
use App\Models\Partida;
use App\Models\Sala;
use App\Services\PartidaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartidaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_aplicar_una_accion_sube_la_version_en_uno(): void
    {
        $sala = Sala::crear();
        $servicio = new PartidaService;
        $partida = $servicio->crear($sala, ['j1', 'j2'], semilla: 123);

        $this->assertSame(1, $partida->fresh()->version);

        $estadoInicial = EstadoPartida::desdeArray($partida->estado);
        $turno = $estadoInicial->turnoActual;
        $mazoAntes = count($estadoInicial->mazo);

        $actualizada = $servicio->aplicarAccion(
            $partida->id,
            fn (EstadoPartida $e) => MotorRumi::comerDelMazo($e, $turno)
        );

        $this->assertSame(2, $actualizada->version);
        $this->assertSame(2, $partida->fresh()->version);

        $estadoNuevo = EstadoPartida::desdeArray($actualizada->estado);
        $this->assertCount($mazoAntes - 1, $estadoNuevo->mazo);
        $this->assertCount(13, $estadoNuevo->jugadores[$turno]->mano);
    }

    public function test_dos_acciones_seguidas_no_se_pisan(): void
    {
        $sala = Sala::crear();
        $servicio = new PartidaService;
        $partida = $servicio->crear($sala, ['j1', 'j2'], semilla: 456);

        $estadoInicial = EstadoPartida::desdeArray($partida->estado);
        $turno = $estadoInicial->turnoActual;
        $mazoAntes = count($estadoInicial->mazo);
        $basuraAntes = count($estadoInicial->basura);

        // Acción 1: comer del mazo.
        $trasComer = $servicio->aplicarAccion(
            $partida->id,
            fn (EstadoPartida $e) => MotorRumi::comerDelMazo($e, $turno)
        );
        $this->assertSame(2, $trasComer->version);

        $estadoTrasComer = EstadoPartida::desdeArray($trasComer->estado);
        $cartaABotar = $estadoTrasComer->jugadores[$turno]->mano[0]->id;

        // Acción 2: botar, encadenada sobre el resultado de la acción 1 (no sobre el estado original).
        $trasBotar = $servicio->aplicarAccion(
            $partida->id,
            fn (EstadoPartida $e) => MotorRumi::botar($e, $turno, $cartaABotar)
        );

        $this->assertSame(3, $trasBotar->version);
        $this->assertSame(3, $partida->fresh()->version);

        $estadoFinal = EstadoPartida::desdeArray($trasBotar->estado);

        // si una acción hubiera pisado a la otra, o el mazo no habría bajado, o la basura no habría subido.
        $this->assertCount($mazoAntes - 1, $estadoFinal->mazo);
        $this->assertCount($basuraAntes + 1, $estadoFinal->basura);
        $this->assertCount(12, $estadoFinal->jugadores[$turno]->mano);
        $this->assertNotSame($turno, $estadoFinal->turnoActual, 'el turno debe haber avanzado tras el botar');
    }

    public function test_vista_para_jugador_no_confunde_ids_numericos(): void
    {
        // PHP coerciona claves de array que parecen enteros ("1", "2") a int; si
        // vistaParaJugador comparara esa clave contra el jugadorId con === sin
        // convertir a string, le mandaría la mano del jugador equivocado.
        $estado = EstadoPartida::iniciar(['1', '2'], semilla: 9);
        $servicio = new PartidaService;

        $vista = $servicio->vistaParaJugador($estado, '1');

        $this->assertArrayHasKey('mano', $vista['jugadores']['1']);
        $this->assertArrayNotHasKey('mano', $vista['jugadores']['2']);
        $this->assertSame(12, $vista['jugadores']['2']['cantidadCartas']);
    }
}
