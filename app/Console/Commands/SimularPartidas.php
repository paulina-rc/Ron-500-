<?php

namespace App\Console\Commands;

use App\Console\Commands\Simulacion\AccionFallidaException;
use App\Console\Commands\Simulacion\EstrategiaSimple;
use App\Console\Commands\Simulacion\LimiteDeRondasExcedido;
use App\Console\Commands\Simulacion\LimiteDeTurnosExcedido;
use App\Domain\Rumi\Carta;
use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\MotorRumi;
use Illuminate\Console\Command;

class SimularPartidas extends Command
{
    protected $signature = 'rumi:simular
        {cantidad=100 : Cuántas partidas correr}
        {--semilla= : Corré una sola partida con esta semilla exacta (para reproducir un fallo)}';

    protected $description = 'Simula partidas completas de Rumi Quinientos con dos jugadores automáticos, para encontrar bugs del motor.';

    private const TOPE_TURNOS_POR_RONDA = 500;

    private const TOPE_RONDAS_POR_PARTIDA = 200;

    private const JUGADORES = ['j1', 'j2'];

    private const PUNTAJE_META = 500;

    public function handle(): int
    {
        if ($this->option('semilla') !== null) {
            $semilla = (int) $this->option('semilla');
            $this->info("Reproduciendo una sola partida con semilla={$semilla}...");
            $this->correrPartida(1, $semilla, verboso: true);
            $this->mostrarResumen();

            return self::SUCCESS;
        }

        $cantidad = max(1, (int) $this->argument('cantidad'));
        $this->info("Simulando {$cantidad} partidas...");

        for ($n = 1; $n <= $cantidad; $n++) {
            $this->correrPartida($n, random_int(1, PHP_INT_MAX));
        }

        $this->mostrarResumen();

        return self::SUCCESS;
    }

    // --- estado acumulado del reporte final -------------------------------

    private int $partidasOk = 0;

    private int $rondasTotales = 0;

    private int $rondasPorGane = 0;

    private int $rondasPorBloqueo = 0;

    /** @var array<string, int> */
    private array $sumaPuntajeFinal = ['j1' => 0, 'j2' => 0];

    /** @var array{numeroPartida:int,semilla:int,rondasJugadas:int,puntajes:array<string,int>}[] */
    private array $abortadasPorRondas = [];

    /** @var array{numeroPartida:int,semilla:int,numeroRonda:int,turnos:int,estado:EstadoPartida}[] */
    private array $abortadasPorTurnos = [];

    /** @var array{numeroPartida:int,semilla:int,jugadorId:string,accion:string,error:string,estado:EstadoPartida}[] */
    private array $excepciones = [];

    /** Cuántas veces ya se mostró el detalle completo (con volcado de estado) de cada firma de fallo distinta. */
    private array $detallesMostradosPorFirma = [];

    private const MAX_DETALLE_POR_FIRMA = 2;

    private function correrPartida(int $numeroPartida, int $semilla, bool $verboso = false): void
    {
        try {
            $resultado = $this->simularPartida($numeroPartida, $semilla);

            $this->partidasOk++;
            $this->rondasTotales += $resultado['rondasJugadas'];
            $this->rondasPorGane += $resultado['ganes'];
            $this->rondasPorBloqueo += $resultado['bloqueos'];
            foreach (self::JUGADORES as $id) {
                $this->sumaPuntajeFinal[$id] += $resultado['puntajes'][$id];
            }

            if ($verboso) {
                $this->line("Partida OK. Rondas jugadas: {$resultado['rondasJugadas']}. Puntajes finales: ".
                    json_encode($resultado['puntajes']));
            }
        } catch (LimiteDeRondasExcedido $e) {
            $this->abortadasPorRondas[] = [
                'numeroPartida' => $e->numeroPartida,
                'semilla' => $e->semilla,
                'rondasJugadas' => $e->rondasJugadas,
                'puntajes' => $e->puntajes,
            ];
            $this->error("[Partida #{$e->numeroPartida}, semilla={$e->semilla}] {$e->getMessage()}");
        } catch (LimiteDeTurnosExcedido $e) {
            $this->abortadasPorTurnos[] = [
                'numeroPartida' => $e->numeroPartida,
                'semilla' => $e->semilla,
                'numeroRonda' => $e->numeroRonda,
                'turnos' => $e->turnos,
                'estado' => $e->estado,
            ];
            $this->error("[Partida #{$e->numeroPartida}, semilla={$e->semilla}] {$e->getMessage()}");
            $this->mostrarDetalleSiCorresponde('limite_turnos', function () use ($e) {
                $this->line($this->describirEstado($e->estado));
            });
        } catch (AccionFallidaException $e) {
            $mensajePrevia = get_class($e->getPrevious()).': '.$e->getPrevious()->getMessage();
            $this->excepciones[] = [
                'numeroPartida' => $numeroPartida,
                'semilla' => $semilla,
                'jugadorId' => $e->jugadorId,
                'accion' => $e->accion,
                'error' => $mensajePrevia,
                'estado' => $e->estadoAntes,
            ];
            $this->error(
                "[Partida #{$numeroPartida}, semilla={$semilla}] Reventó en la acción '{$e->accion}' ".
                "del jugador {$e->jugadorId}: {$mensajePrevia}"
            );
            $this->mostrarDetalleSiCorresponde("accion:{$e->accion}|{$mensajePrevia}", function () use ($e) {
                $this->line('Estado justo antes de esa acción:');
                $this->line($this->describirEstado($e->estadoAntes));
            });
        } catch (\Throwable $e) {
            $this->excepciones[] = [
                'numeroPartida' => $numeroPartida,
                'semilla' => $semilla,
                'jugadorId' => '?',
                'accion' => '(fuera de un turno)',
                'error' => get_class($e).': '.$e->getMessage(),
                'estado' => null,
            ];
            $this->error("[Partida #{$numeroPartida}, semilla={$semilla}] Excepción inesperada: ".get_class($e).': '.$e->getMessage());
        }
    }

    /**
     * @return array{rondasJugadas:int, ganes:int, bloqueos:int, puntajes:array<string,int>}
     */
    private function simularPartida(int $numeroPartida, int $semilla): array
    {
        $puntajes = ['j1' => 0, 'j2' => 0];
        $rondasJugadas = 0;
        $ganes = 0;
        $bloqueos = 0;

        for ($numeroRonda = 1; $numeroRonda <= self::TOPE_RONDAS_POR_PARTIDA; $numeroRonda++) {
            $semillaRonda = $semilla + $numeroRonda;
            $estado = EstadoPartida::iniciar(self::JUGADORES, $semillaRonda);

            $turnos = 0;
            while (! $estado->rondaTerminada) {
                $turnos++;
                if ($turnos > self::TOPE_TURNOS_POR_RONDA) {
                    throw new LimiteDeTurnosExcedido($estado, $numeroPartida, $numeroRonda, $semilla, $turnos - 1);
                }
                $estado = EstrategiaSimple::jugarTurno($estado);
            }

            $rondasJugadas++;
            $estado->ganadorRonda !== null ? $ganes++ : $bloqueos++;

            foreach (self::JUGADORES as $id) {
                $puntajes[$id] += MotorRumi::puntosDeRonda($estado, $id);
            }

            if (max($puntajes) >= self::PUNTAJE_META) {
                return compact('rondasJugadas', 'ganes', 'bloqueos', 'puntajes');
            }
        }

        throw new LimiteDeRondasExcedido($puntajes, $rondasJugadas, $numeroPartida, $semilla);
    }

    private function mostrarResumen(): void
    {
        $this->newLine();
        $this->info('=== Resumen ===');

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Partidas jugadas (completas)', $this->partidasOk],
                ['Rondas totales', $this->rondasTotales],
                ['Promedio de rondas por partida', $this->partidasOk > 0 ? round($this->rondasTotales / $this->partidasOk, 2) : '-'],
                ['Rondas terminadas por gane', $this->rondasPorGane],
                ['Rondas terminadas porque nadie podía comer', $this->rondasPorBloqueo],
                ['Puntaje final promedio j1', $this->partidasOk > 0 ? round($this->sumaPuntajeFinal['j1'] / $this->partidasOk, 2) : '-'],
                ['Puntaje final promedio j2', $this->partidasOk > 0 ? round($this->sumaPuntajeFinal['j2'] / $this->partidasOk, 2) : '-'],
                ['Partidas abortadas por tope de rondas', count($this->abortadasPorRondas)],
                ['Rondas abortadas por tope de turnos', count($this->abortadasPorTurnos)],
                ['Excepciones inesperadas', count($this->excepciones)],
            ]
        );

        if (count($this->excepciones) > 0) {
            $this->newLine();
            $this->warn('Excepciones inesperadas, agrupadas por tipo de fallo:');
            $porFirma = [];
            foreach ($this->excepciones as $f) {
                $firma = "{$f['accion']} | {$f['error']}";
                $porFirma[$firma] = ($porFirma[$firma] ?? 0) + 1;
            }
            arsort($porFirma);
            $filas = [];
            foreach ($porFirma as $firma => $cantidad) {
                $filas[] = [$cantidad, $firma];
            }
            $this->table(['Ocurrencias', 'Acción | Error'], $filas);
        }

        $maxSemillasListadas = 15;
        if (count($this->abortadasPorRondas) > 0 || count($this->abortadasPorTurnos) > 0 || count($this->excepciones) > 0) {
            $this->newLine();
            $this->warn('Semillas para reproducir fallos (php artisan rumi:simular --semilla=N):');

            $this->listarSemillas(
                'tope de rondas',
                array_map(fn ($f) => "partida #{$f['numeroPartida']}: semilla={$f['semilla']}", $this->abortadasPorRondas),
                $maxSemillasListadas
            );
            $this->listarSemillas(
                'tope de turnos',
                array_map(
                    fn ($f) => "partida #{$f['numeroPartida']}: semilla={$f['semilla']} (ronda {$f['numeroRonda']})",
                    $this->abortadasPorTurnos
                ),
                $maxSemillasListadas
            );
            $this->listarSemillas(
                'excepciones',
                array_map(fn ($f) => "partida #{$f['numeroPartida']}: semilla={$f['semilla']}", $this->excepciones),
                $maxSemillasListadas
            );
        }
    }

    /** @param string[] $lineas */
    private function listarSemillas(string $etiqueta, array $lineas, int $maximo): void
    {
        if (count($lineas) === 0) {
            return;
        }

        $this->line("  {$etiqueta} (".count($lineas).'):');
        foreach (array_slice($lineas, 0, $maximo) as $linea) {
            $this->line("    - {$linea}");
        }
        if (count($lineas) > $maximo) {
            $this->line('    ... y '.(count($lineas) - $maximo).' más.');
        }
    }

    /** Muestra el detalle completo (con volcado de estado) solo las primeras veces por cada firma de fallo distinta. */
    private function mostrarDetalleSiCorresponde(string $firma, callable $imprimirDetalle): void
    {
        $vistas = $this->detallesMostradosPorFirma[$firma] ?? 0;
        if ($vistas >= self::MAX_DETALLE_POR_FIRMA) {
            $this->line('  (detalle omitido: ya se mostró este mismo tipo de fallo antes; ver semilla arriba para reproducirlo)');

            return;
        }

        $this->detallesMostradosPorFirma[$firma] = $vistas + 1;
        $imprimirDetalle();
    }

    private function describirEstado(EstadoPartida $estado): string
    {
        $lineas = [];
        $obligatoria = $estado->cartaObligatoriaId ?? '-';
        $lineas[] = "Turno actual: {$estado->turnoActual} | Fase: {$estado->fase} | Carta obligatoria: {$obligatoria}";
        $lineas[] = 'Mazo ('.count($estado->mazo).' cartas): '.$this->idsDeCartas($estado->mazo);
        $lineas[] = 'Basura ('.count($estado->basura).' cartas, de abajo a arriba): '.$this->idsDeCartas($estado->basura);

        foreach ($estado->jugadores as $id => $jugador) {
            $bajado = $jugador->seHaBajado ? 'sí' : 'no';
            $lineas[] = "Mano de {$id} (".count($jugador->mano).' cartas, bajado='.$bajado.'): '.$this->idsDeCartas($jugador->mano);
        }

        if (count($estado->mesa) === 0) {
            $lineas[] = 'Mesa: vacía';
        } else {
            $lineas[] = 'Mesa:';
            foreach ($estado->mesa as $juegoId => $juego) {
                $cartasTxt = implode(', ', array_map(
                    fn ($c) => "{$c->carta->id}({$c->propietarioId})",
                    $juego->cartas
                ));
                $lineas[] = "  {$juegoId} [{$juego->tipo}]: {$cartasTxt}";
            }
        }

        return implode("\n", $lineas);
    }

    /** @param Carta[] $cartas */
    private function idsDeCartas(array $cartas): string
    {
        return count($cartas) > 0 ? implode(', ', array_map(fn (Carta $c) => $c->id, $cartas)) : '(ninguna)';
    }
}
