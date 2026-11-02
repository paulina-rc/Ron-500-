<?php

namespace App\Services;

use App\Domain\Rumi\Carta;
use App\Domain\Rumi\CartaEnMesa;
use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\Excepciones\JugadaIlegalException;
use App\Domain\Rumi\MotorRumi;
use App\Domain\Rumi\Reglas;
use App\Models\Partida;
use App\Models\Sala;
use Illuminate\Support\Facades\DB;

/**
 * Junta el motor de reglas (App\Domain\Rumi) con la persistencia (Eloquent):
 * carga el estado de una partida, le aplica una acción del motor, lo guarda
 * y sube la version. El motor en sí no sabe que esta clase existe.
 */
class PartidaService
{
    /**
     * Crea la partida (ronda) inicial de una sala: reparte con el motor y
     * guarda el estado resultante con version=1.
     *
     * @param string[] $idsJugadores
     */
    public function crear(Sala $sala, array $idsJugadores, ?int $semilla = null, int $versionInicial = 1): Partida
    {
        $estado = EstadoPartida::iniciar($idsJugadores, $semilla);

        return Partida::create([
            'sala_id' => $sala->id,
            'estado' => $estado->aArray(),
            'version' => $versionInicial,
        ]);
    }

    /**
     * Reparte la ronda siguiente de $sala. Cada ronda la empieza el siguiente
     * jugador en orden de puesto (se alterna). Es idempotente: si los dos
     * jugadores tocan "Siguiente ronda" a la vez, solo se crea una.
     *
     * La version de la ronda nueva sigue la numeración de la anterior, para que
     * el polling de los clientes nunca confunda un estado viejo con uno nuevo.
     */
    public function siguienteRonda(Sala $sala, ?int $semilla = null): Partida
    {
        return DB::transaction(function () use ($sala, $semilla) {
            $sala = Sala::query()->lockForUpdate()->findOrFail($sala->id);
            $ultima = $sala->partidas()->latest('id')->firstOrFail();
            $estadoUltima = EstadoPartida::desdeArray($ultima->estado);

            if (! $estadoUltima->rondaTerminada) {
                return $ultima; // otro jugador ya la arrancó
            }

            if ($this->ganadorDeLaPartida($sala) !== null) {
                throw new JugadaIlegalException('La partida ya terminó.');
            }

            $ids = $this->idsJugadoresEnOrden($sala);
            $numeroRonda = $sala->partidas()->count() + 1;
            $desfase = ($numeroRonda - 1) % count($ids);
            $orden = array_merge(array_slice($ids, $desfase), array_slice($ids, 0, $desfase));

            return $this->crear($sala, $orden, $semilla, $ultima->version + 1);
        });
    }

    /**
     * Puntos de cada ronda jugada en $sala y el total acumulado. Solo las rondas
     * terminadas cuentan para el total; la ronda en curso va aparte.
     *
     * @return array{rondas: list<array{numero: int, terminada: bool, ganadorRonda: ?string, puntos: array<string, array{mesa: int, gane: int, mano: int, total: int}>}>, totales: array<string, int>}
     */
    public function marcador(Sala $sala): array
    {
        $ids = $this->idsJugadoresEnOrden($sala);
        $totales = array_fill_keys($ids, 0);
        $rondas = [];

        foreach ($sala->partidas()->orderBy('id')->get() as $i => $partida) {
            $estado = EstadoPartida::desdeArray($partida->estado);
            $puntos = [];
            foreach ($ids as $id) {
                $puntos[$id] = MotorRumi::desgloseDeRonda($estado, $id);
                if ($estado->rondaTerminada) {
                    $totales[$id] += $puntos[$id]['total'];
                }
            }

            $rondas[] = [
                'numero' => $i + 1,
                'terminada' => $estado->rondaTerminada,
                'ganadorRonda' => $estado->ganadorRonda,
                'puntos' => $puntos,
            ];
        }

        return ['rondas' => $rondas, 'totales' => $totales];
    }

    /** El ganador de la partida completa, o null si todavía no hay (o la ronda sigue en curso). */
    public function ganadorDeLaPartida(Sala $sala): ?string
    {
        $ultima = $sala->partidas()->latest('id')->first();
        if ($ultima === null || ! EstadoPartida::desdeArray($ultima->estado)->rondaTerminada) {
            return null;
        }

        return Reglas::ganadorDeLaPartida($this->marcador($sala)['totales']);
    }

    /** Pasa la sala a TERMINADA si alguien ya ganó la partida. */
    public function cerrarSalaSiHayGanador(Sala $sala): void
    {
        if ($sala->estado !== Sala::TERMINADA && $this->ganadorDeLaPartida($sala) !== null) {
            $sala->estado = Sala::TERMINADA;
            $sala->save();
        }
    }

    /**
     * Todo lo que un jugador necesita para dibujar la pantalla: la vista de la
     * ronda actual (sin la mano ajena) más nombres, número de ronda, marcador
     * acumulado y ganador de la partida.
     */
    public function respuestaParaJugador(Sala $sala, Partida $partida, string $jugadorId): array
    {
        $estado = EstadoPartida::desdeArray($partida->estado);
        $marcador = $this->marcador($sala);
        $ganador = $estado->rondaTerminada ? Reglas::ganadorDeLaPartida($marcador['totales']) : null;

        $nombres = [];
        foreach ($sala->jugadores()->orderBy('puesto')->get() as $jugador) {
            $nombres[$jugador->idDominio()] = $jugador->nombre;
        }

        return array_merge(
            [
                'version' => $partida->version,
                'salaEstado' => $sala->estado,
                'ronda' => count($marcador['rondas']),
                'nombres' => $nombres,
                'marcador' => $marcador,
                'ganadorPartida' => $ganador,
                'meta' => Reglas::META_PARTIDA,
            ],
            $this->vistaParaJugador($estado, $jugadorId),
        );
    }

    /** @return string[] */
    private function idsJugadoresEnOrden(Sala $sala): array
    {
        return $sala->jugadores()->orderBy('puesto')->get()
            ->map(fn ($j) => $j->idDominio())
            ->all();
    }

    /**
     * Carga el estado vigente de $partidaId con lock pesimista, le aplica $accion
     * (que recibe el EstadoPartida actual y devuelve el nuevo), lo guarda y sube
     * la version en 1. Todo dentro de una transacción, para que dos peticiones
     * concurrentes sobre la misma partida no se pisen.
     *
     * @param callable(EstadoPartida): EstadoPartida $accion
     */
    public function aplicarAccion(int $partidaId, callable $accion): Partida
    {
        return DB::transaction(function () use ($partidaId, $accion) {
            $partida = Partida::query()->lockForUpdate()->findOrFail($partidaId);

            $estadoActual = EstadoPartida::desdeArray($partida->estado);
            $estadoNuevo = $accion($estadoActual);

            $partida->estado = $estadoNuevo->aArray();
            $partida->version = $partida->version + 1;
            $partida->save();

            return $partida;
        });
    }

    /**
     * Arma la vista de $estado para $jugadorId: su mano completa, pero de cualquier
     * otro jugador solo la cantidad de cartas. La basura y la mesa se ven completas
     * (son públicas por regla), cada carta de mesa con su propietario.
     */
    public function vistaParaJugador(EstadoPartida $estado, string $jugadorId): array
    {
        $jugadores = [];
        foreach ($estado->jugadores as $id => $jugador) {
            // $id puede venir como int si el id de jugador es numérico (PHP coerciona
            // claves de array que parecen enteros): comparar como string siempre.
            $jugadores[$id] = (string) $id === $jugadorId
                ? [
                    'mano' => array_map(fn (Carta $c) => $this->cartaVisible($c), $jugador->mano),
                    'seHaBajado' => $jugador->seHaBajado,
                ]
                : [
                    'cantidadCartas' => count($jugador->mano),
                    'seHaBajado' => $jugador->seHaBajado,
                ];
        }

        $mesa = [];
        foreach ($estado->mesa as $id => $juego) {
            $mesa[$id] = [
                'tipo' => $juego->tipo,
                'creadorId' => $juego->creadorId,
                'cartas' => array_map(fn (CartaEnMesa $c) => [
                    'carta' => $this->cartaVisible($c->carta),
                    'propietarioId' => $c->propietarioId,
                ], $juego->cartas),
            ];
        }

        $puntajes = [];
        foreach ($estado->idsJugadores() as $id) {
            $puntajes[$id] = MotorRumi::puntosDeRonda($estado, $id);
        }

        return [
            'turnoActual' => $estado->turnoActual,
            'fase' => $estado->fase,
            'cartaObligatoriaId' => $estado->cartaObligatoriaId,
            'rondaTerminada' => $estado->rondaTerminada,
            'ganadorRonda' => $estado->ganadorRonda,
            'cartasEnMazo' => count($estado->mazo),
            'basura' => array_map(fn (Carta $c) => $this->cartaVisible($c), $estado->basura),
            'mesa' => $mesa,
            'jugadores' => $jugadores,
            'puntajes' => $puntajes,
        ];
    }

    private function cartaVisible(Carta $carta): array
    {
        return [
            'id' => $carta->id,
            'numero' => $carta->numero,
            'palo' => $carta->palo,
            'puntos' => $carta->puntos(),
        ];
    }
}
