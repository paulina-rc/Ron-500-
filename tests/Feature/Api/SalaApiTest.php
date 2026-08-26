<?php

namespace Tests\Feature\Api;

use App\Domain\Rumi\EstadoPartida;
use App\Models\Sala;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_sala_devuelve_codigo_y_token(): void
    {
        $respuesta = $this->postJson('/api/salas', ['nombre' => 'Paulina']);

        $respuesta->assertCreated();
        $respuesta->assertJsonStructure(['codigo', 'token', 'jugadorId', 'puesto']);
        $this->assertMatchesRegularExpression('/^[A-Z]{4}$/', $respuesta->json('codigo'));
        $this->assertNotEmpty($respuesta->json('token'));
        $this->assertSame(0, $respuesta->json('puesto'));
    }

    public function test_unirse_arranca_la_partida_y_reparte(): void
    {
        $creado = $this->postJson('/api/salas', ['nombre' => 'Paulina'])->json();

        $respuesta = $this->postJson("/api/salas/{$creado['codigo']}/unirse", ['nombre' => 'Hilda']);

        $respuesta->assertCreated();
        $respuesta->assertJsonStructure(['token', 'jugadorId', 'puesto']);
        $this->assertSame(1, $respuesta->json('puesto'));
        $this->assertNotSame($creado['token'], $respuesta->json('token'));

        $sala = Sala::where('codigo', $creado['codigo'])->first();
        $this->assertSame(Sala::JUGANDO, $sala->estado);

        $partida = $sala->partidas()->first();
        $this->assertNotNull($partida);
        $this->assertSame(1, $partida->version);

        $estado = EstadoPartida::desdeArray($partida->estado);
        $this->assertCount(12, $estado->jugadores[(string) $creado['jugadorId']]->mano);
        $this->assertCount(12, $estado->jugadores[(string) $respuesta->json('jugadorId')]->mano);
        $this->assertCount(1, $estado->basura);
    }

    public function test_unirse_rechaza_si_la_sala_no_existe(): void
    {
        $this->postJson('/api/salas/ZZZZ/unirse', ['nombre' => 'Hilda'])->assertNotFound();
    }

    public function test_unirse_rechaza_si_la_sala_ya_esta_llena(): void
    {
        $creado = $this->postJson('/api/salas', ['nombre' => 'Paulina'])->json();
        $this->postJson("/api/salas/{$creado['codigo']}/unirse", ['nombre' => 'Hilda'])->assertCreated();

        $this->postJson("/api/salas/{$creado['codigo']}/unirse", ['nombre' => 'Otro'])
            ->assertStatus(422);
    }

    public function test_el_estado_nunca_incluye_la_mano_del_rival_solo_su_cantidad(): void
    {
        [$sala, $j1, $j2] = $this->crearSalaConDosJugadores();

        $respuesta = $this->getJson("/api/salas/{$sala->codigo}/estado?version=0", [
            'X-Jugador-Token' => $j1['token'],
        ]);

        $respuesta->assertOk();
        $cuerpo = $respuesta->json();

        $this->assertArrayHasKey('mano', $cuerpo['jugadores'][(string) $j1['id']]);
        $this->assertArrayNotHasKey('mano', $cuerpo['jugadores'][(string) $j2['id']]);
        $this->assertSame(12, $cuerpo['jugadores'][(string) $j2['id']]['cantidadCartas']);

        // ninguna carta real del rival puede aparecer en el cuerpo de la respuesta, ni disfrazada
        $manoRival = EstadoPartida::desdeArray($sala->partidas()->first()->estado)
            ->jugadores[(string) $j2['id']]->mano;

        foreach ($manoRival as $carta) {
            $this->assertStringNotContainsString($carta->id, $respuesta->getContent());
        }
    }

    public function test_con_la_misma_version_responde_204(): void
    {
        [$sala, $j1] = $this->crearSalaConDosJugadores();

        $primera = $this->getJson("/api/salas/{$sala->codigo}/estado?version=0", [
            'X-Jugador-Token' => $j1['token'],
        ]);
        $primera->assertOk();
        $version = $primera->json('version');

        $segunda = $this->getJson("/api/salas/{$sala->codigo}/estado?version={$version}", [
            'X-Jugador-Token' => $j1['token'],
        ]);
        $segunda->assertNoContent();
    }

    public function test_una_accion_sube_la_version_y_el_siguiente_polling_trae_el_cambio(): void
    {
        [$sala, $j1, $j2, $tokenTurno] = $this->crearSalaConDosJugadoresYTurno();

        $antes = $this->getJson("/api/salas/{$sala->codigo}/estado?version=0", [
            'X-Jugador-Token' => $tokenTurno,
        ]);
        $versionAntes = $antes->json('version');

        $respuestaAccion = $this->postJson(
            "/api/salas/{$sala->codigo}/accion",
            ['accion' => 'comer_mazo'],
            ['X-Jugador-Token' => $tokenTurno]
        );
        $respuestaAccion->assertOk();
        $this->assertSame($versionAntes + 1, $respuestaAccion->json('version'));

        // el siguiente polling, todavía con la version vieja, ya debe traer el cambio (no 204)
        $siguiente = $this->getJson("/api/salas/{$sala->codigo}/estado?version={$versionAntes}", [
            'X-Jugador-Token' => $tokenTurno,
        ]);
        $siguiente->assertOk();
        $this->assertSame($versionAntes + 1, $siguiente->json('version'));
    }

    public function test_una_accion_con_token_de_otro_jugador_se_rechaza(): void
    {
        [$salaA] = $this->crearSalaConDosJugadores();
        [, $bAjeno] = $this->crearSalaConDosJugadores();

        $respuesta = $this->postJson(
            "/api/salas/{$salaA->codigo}/accion",
            ['accion' => 'comer_mazo'],
            ['X-Jugador-Token' => $bAjeno['token']]
        );

        $respuesta->assertStatus(401);
    }

    public function test_jugar_fuera_de_turno_devuelve_422_con_el_mensaje_del_motor(): void
    {
        [$sala, , , , $tokenFueraDeTurno] = $this->crearSalaConDosJugadoresYTurno();

        $respuesta = $this->postJson(
            "/api/salas/{$sala->codigo}/accion",
            ['accion' => 'comer_mazo'],
            ['X-Jugador-Token' => $tokenFueraDeTurno]
        );

        $respuesta->assertStatus(422);
        $this->assertSame('No es tu turno.', $respuesta->json('message'));
    }

    public function test_una_accion_ilegal_no_cambia_la_version(): void
    {
        [$sala, , , , $tokenFueraDeTurno] = $this->crearSalaConDosJugadoresYTurno();
        $versionAntes = $sala->partidas()->first()->version;

        $this->postJson(
            "/api/salas/{$sala->codigo}/accion",
            ['accion' => 'comer_mazo'],
            ['X-Jugador-Token' => $tokenFueraDeTurno]
        )->assertStatus(422);

        $this->assertSame($versionAntes, $sala->partidas()->first()->fresh()->version);
    }

    /** @return array{0: Sala, 1: array{id:int,token:string}, 2: array{id:int,token:string}} */
    private function crearSalaConDosJugadores(): array
    {
        $creado = $this->postJson('/api/salas', ['nombre' => 'Paulina'])->json();
        $segundo = $this->postJson("/api/salas/{$creado['codigo']}/unirse", ['nombre' => 'Hilda'])->json();

        $sala = Sala::where('codigo', $creado['codigo'])->first();

        return [
            $sala,
            ['id' => $creado['jugadorId'], 'token' => $creado['token']],
            ['id' => $segundo['jugadorId'], 'token' => $segundo['token']],
        ];
    }

    /**
     * Igual que crearSalaConDosJugadores(), pero además identifica cuál token es
     * el del jugador en turno y cuál el del que NO le toca todavía.
     *
     * @return array{0: Sala, 1: array, 2: array, 3: string, 4: string}
     */
    private function crearSalaConDosJugadoresYTurno(): array
    {
        [$sala, $j1, $j2] = $this->crearSalaConDosJugadores();

        $estado = EstadoPartida::desdeArray($sala->partidas()->first()->estado);
        $turno = $estado->turnoActual;

        $tokenTurno = $turno === (string) $j1['id'] ? $j1['token'] : $j2['token'];
        $tokenFueraDeTurno = $turno === (string) $j1['id'] ? $j2['token'] : $j1['token'];

        return [$sala, $j1, $j2, $tokenTurno, $tokenFueraDeTurno];
    }
}
