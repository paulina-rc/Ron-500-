<?php

namespace App\Services;

use App\Domain\Rumi\Carta;
use App\Domain\Rumi\CartaEnMesa;
use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\MotorRumi;
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
    public function crear(Sala $sala, array $idsJugadores, ?int $semilla = null): Partida
    {
        $estado = EstadoPartida::iniciar($idsJugadores, $semilla);

        return Partida::create([
            'sala_id' => $sala->id,
            'estado' => $estado->aArray(),
            'version' => 1,
        ]);
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
