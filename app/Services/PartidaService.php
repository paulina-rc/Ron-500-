<?php

namespace App\Services;

use App\Domain\Rumi\EstadoPartida;
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
}
