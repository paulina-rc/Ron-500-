<?php

namespace Tests\Feature\Api;

use App\Domain\Rumi\CartaEnMesa;
use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\JuegoMesa;
use App\Domain\Rumi\MotorRumi;
use App\Models\Sala;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RondasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_estado_trae_nombres_ronda_y_marcador(): void
    {
        [$sala, $j1, $j2] = $this->crearSala();

        $cuerpo = $this->estado($sala, $j1['token'])->json();

        $this->assertSame(1, $cuerpo['ronda']);
        $this->assertSame('Paulina', $cuerpo['nombres'][$j1['id']]);
        $this->assertSame('Hilda', $cuerpo['nombres'][$j2['id']]);
        $this->assertSame([$j1['id'] => 0, $j2['id'] => 0], $cuerpo['marcador']['totales']);
        $this->assertNull($cuerpo['ganadorPartida']);
        $this->assertSame(500, $cuerpo['meta']);
    }

    public function test_no_se_puede_pasar_de_ronda_si_la_actual_sigue(): void
    {
        [$sala, $j1] = $this->crearSala();

        $respuesta = $this->siguiente($sala, $j1['token']);

        // idempotente: devuelve la misma ronda en curso, no crea otra
        $respuesta->assertOk();
        $this->assertSame(1, $respuesta->json('ronda'));
        $this->assertSame(1, $sala->partidas()->count());
    }

    public function test_siguiente_ronda_acumula_puntos_y_alterna_quien_empieza(): void
    {
        [$sala, $j1, $j2] = $this->crearSala();
        $empezoRonda1 = $this->estadoActual($sala)->turnoActual;

        $this->terminarRonda($sala, ganador: $j1['id'], cartasEnMesa: 3);
        $puntosRonda1 = $this->estado($sala, $j1['token'])->json('puntajes');

        $respuesta = $this->siguiente($sala, $j2['token']);

        $respuesta->assertOk();
        $this->assertSame(2, $respuesta->json('ronda'));
        $this->assertSame(2, $sala->partidas()->count());
        $this->assertFalse($respuesta->json('rondaTerminada'));
        $this->assertSame($puntosRonda1[$j1['id']], $respuesta->json('marcador.totales.'.$j1['id']));
        $this->assertSame($puntosRonda1[$j2['id']], $respuesta->json('marcador.totales.'.$j2['id']));
        $this->assertCount(12, $respuesta->json('jugadores.'.$j2['id'].'.mano'));

        $this->assertNotSame($empezoRonda1, $this->estadoActual($sala)->turnoActual);
    }

    public function test_los_dos_tocan_siguiente_ronda_y_solo_se_crea_una(): void
    {
        [$sala, $j1, $j2] = $this->crearSala();
        $this->terminarRonda($sala, ganador: $j1['id'], cartasEnMesa: 3);

        $this->siguiente($sala, $j1['token'])->assertOk();
        $this->siguiente($sala, $j2['token'])->assertOk();

        $this->assertSame(2, $sala->partidas()->count());
    }

    public function test_la_version_sigue_subiendo_entre_rondas(): void
    {
        [$sala, $j1] = $this->crearSala();
        $this->terminarRonda($sala, ganador: $j1['id'], cartasEnMesa: 3);
        $versionVieja = $sala->partidas()->latest('id')->first()->version;

        $nueva = $this->siguiente($sala, $j1['token'])->json('version');

        $this->assertGreaterThan($versionVieja, $nueva);
    }

    public function test_al_pasar_de_500_termina_la_partida_y_no_hay_mas_rondas(): void
    {
        [$sala, $j1, $j2] = $this->crearSala();
        $this->terminarRonda($sala, ganador: $j1['id'], cartasEnMesa: 70);

        // la sala se cierra con la siguiente acción o consulta del servicio
        app(\App\Services\PartidaService::class)->cerrarSalaSiHayGanador($sala);

        $cuerpo = $this->estado($sala, $j2['token'])->json();
        $this->assertSame($j1['id'], $cuerpo['ganadorPartida']);
        $this->assertSame(Sala::TERMINADA, $cuerpo['salaEstado']);
        $this->assertGreaterThanOrEqual(500, $cuerpo['marcador']['totales'][$j1['id']]);

        $this->siguiente($sala, $j1['token'])->assertStatus(422);
        $this->assertSame(1, $sala->partidas()->count());
    }

    public function test_botar_la_ultima_carta_con_500_cierra_la_sala(): void
    {
        [$sala, $j1] = $this->crearSala();

        // j1 tiene muchos puntos en mesa, le toca, ya comió, y le queda una sola carta
        $partida = $sala->partidas()->first();
        $estado = EstadoPartida::desdeArray($partida->estado);
        $id = $j1['id'];
        $cartas = array_splice($estado->mazo, 0, 70);
        $estado->mesa['j1'] = new JuegoMesa('j1', JuegoMesa::TRIO, $id,
            array_map(fn ($c) => new CartaEnMesa($c, $id), $cartas));
        $estado->jugadores[$id]->mano = array_slice($estado->jugadores[$id]->mano, 0, 1);
        $estado->jugadores[$id]->seHaBajado = true;
        $estado->turnoActual = $id;
        $estado->fase = EstadoPartida::PUEDE_JUGAR;
        $partida->estado = $estado->aArray();
        $partida->save();

        $ultima = $estado->jugadores[$id]->mano[0]->id;
        $respuesta = $this->postJson("/api/salas/{$sala->codigo}/accion",
            ['accion' => 'botar', 'cartaId' => $ultima], ['X-Jugador-Token' => $j1['token']]);

        $respuesta->assertOk();
        $this->assertTrue($respuesta->json('rondaTerminada'));
        $this->assertSame($id, $respuesta->json('ganadorPartida'));
        $this->assertSame(Sala::TERMINADA, $sala->fresh()->estado);
    }

    // ---- ayudantes ----

    /** @return array{0: Sala, 1: array{id:string,token:string}, 2: array{id:string,token:string}} */
    private function crearSala(): array
    {
        $creado = $this->postJson('/api/salas', ['nombre' => 'Paulina'])->json();
        $segundo = $this->postJson("/api/salas/{$creado['codigo']}/unirse", ['nombre' => 'Hilda'])->json();

        return [
            Sala::where('codigo', $creado['codigo'])->first(),
            ['id' => (string) $creado['jugadorId'], 'token' => $creado['token']],
            ['id' => (string) $segundo['jugadorId'], 'token' => $segundo['token']],
        ];
    }

    private function estado(Sala $sala, string $token)
    {
        return $this->getJson("/api/salas/{$sala->codigo}/estado?version=0", ['X-Jugador-Token' => $token]);
    }

    private function siguiente(Sala $sala, string $token)
    {
        return $this->postJson("/api/salas/{$sala->codigo}/siguiente-ronda", [], ['X-Jugador-Token' => $token]);
    }

    private function estadoActual(Sala $sala): EstadoPartida
    {
        return EstadoPartida::desdeArray($sala->partidas()->latest('id')->first()->estado);
    }

    /**
     * Fuerza el final de la ronda en curso: el ganador sale sin cartas y deja en
     * mesa $cartasEnMesa cartas del mazo a su nombre (para controlar los puntos).
     */
    private function terminarRonda(Sala $sala, string $ganador, int $cartasEnMesa): void
    {
        $partida = $sala->partidas()->latest('id')->first();
        $estado = EstadoPartida::desdeArray($partida->estado);

        $cartas = array_splice($estado->mazo, 0, $cartasEnMesa);
        $estado->mesa['j1'] = new JuegoMesa('j1', JuegoMesa::TRIO, $ganador,
            array_map(fn ($c) => new CartaEnMesa($c, $ganador), $cartas));
        $estado->jugadores[$ganador]->mano = [];
        $estado->rondaTerminada = true;
        $estado->ganadorRonda = $ganador;

        $partida->estado = $estado->aArray();
        $partida->version += 1;
        $partida->save();

        $this->assertSame(
            MotorRumi::puntosDeRonda($estado, $ganador),
            MotorRumi::desgloseDeRonda($estado, $ganador)['total']
        );
    }
}
