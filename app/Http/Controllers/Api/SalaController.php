<?php

namespace App\Http\Controllers\Api;

use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\Excepciones\JugadaIlegalException;
use App\Domain\Rumi\MotorRumi;
use App\Http\Controllers\Controller;
use App\Models\Jugador;
use App\Models\Sala;
use App\Services\PartidaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SalaController extends Controller
{
    public function crear(Request $request): JsonResponse
    {
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:40']]);

        $sala = Sala::crear();
        $jugador = Jugador::crear($sala, $datos['nombre'], puesto: 0);

        return response()->json([
            'codigo' => $sala->codigo,
            'token' => $jugador->token,
            'jugadorId' => $jugador->idDominio(),
            'puesto' => $jugador->puesto,
        ], 201);
    }

    public function unirse(Request $request, Sala $sala, PartidaService $servicio): JsonResponse
    {
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:40']]);

        if ($sala->estado !== Sala::ESPERANDO || $sala->jugadores()->count() >= 2) {
            return response()->json(['message' => 'Esa sala ya está llena o la partida ya arrancó.'], 422);
        }

        $primero = $sala->jugadores()->orderBy('puesto')->firstOrFail();
        $segundo = Jugador::crear($sala, $datos['nombre'], puesto: 1);

        $servicio->crear($sala, [$primero->idDominio(), $segundo->idDominio()]);
        $sala->estado = Sala::JUGANDO;
        $sala->save();

        return response()->json([
            'token' => $segundo->token,
            'jugadorId' => $segundo->idDominio(),
            'puesto' => $segundo->puesto,
        ], 201);
    }

    public function estado(Request $request, Sala $sala, PartidaService $servicio): JsonResponse|Response
    {
        $jugador = $this->autenticar($request, $sala);
        if ($jugador instanceof JsonResponse) {
            return $jugador;
        }

        $partida = $sala->partidas()->latest('id')->first();
        if ($partida === null) {
            return response()->json(['version' => 0, 'salaEstado' => $sala->estado, 'yo' => $jugador->idDominio()]);
        }

        $versionCliente = (int) $request->query('version', -1);
        if ($versionCliente === $partida->version) {
            return response()->noContent();
        }

        return response()->json($servicio->respuestaParaJugador($sala, $partida, $jugador->idDominio()));
    }

    public function accion(Request $request, Sala $sala, PartidaService $servicio): JsonResponse
    {
        $jugador = $this->autenticar($request, $sala);
        if ($jugador instanceof JsonResponse) {
            return $jugador;
        }

        $partida = $sala->partidas()->latest('id')->first();
        if ($partida === null) {
            return response()->json(['message' => 'La partida todavía no arrancó.'], 422);
        }

        $reglas = match ($request->input('accion')) {
            'comer_basura' => ['cartaId' => ['required', 'string']],
            'bajar' => ['cartaIds' => ['required', 'array', 'min:1'], 'cartaIds.*' => ['string']],
            'embonar' => ['juegoId' => ['required', 'string'], 'cartaId' => ['required', 'string']],
            'botar' => ['cartaId' => ['required', 'string']],
            default => [],
        };

        $datos = $request->validate(array_merge(
            ['accion' => ['required', 'string', 'in:comer_mazo,comer_basura,bajar,embonar,botar']],
            $reglas
        ));

        $jugadorId = $jugador->idDominio();
        $accionMotor = match ($datos['accion']) {
            'comer_mazo' => fn (EstadoPartida $e) => MotorRumi::comerDelMazo($e, $jugadorId),
            'comer_basura' => fn (EstadoPartida $e) => MotorRumi::comerDeLaBasura($e, $jugadorId, $datos['cartaId']),
            'bajar' => fn (EstadoPartida $e) => MotorRumi::bajar($e, $jugadorId, $datos['cartaIds']),
            'embonar' => fn (EstadoPartida $e) => MotorRumi::embonar($e, $jugadorId, $datos['juegoId'], $datos['cartaId']),
            'botar' => fn (EstadoPartida $e) => MotorRumi::botar($e, $jugadorId, $datos['cartaId']),
        };

        try {
            $partidaActualizada = $servicio->aplicarAccion($partida->id, $accionMotor);
        } catch (JugadaIlegalException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $servicio->cerrarSalaSiHayGanador($sala);

        return response()->json($servicio->respuestaParaJugador($sala->refresh(), $partidaActualizada, $jugadorId));
    }

    public function siguienteRonda(Request $request, Sala $sala, PartidaService $servicio): JsonResponse
    {
        $jugador = $this->autenticar($request, $sala);
        if ($jugador instanceof JsonResponse) {
            return $jugador;
        }

        if ($sala->partidas()->doesntExist()) {
            return response()->json(['message' => 'La partida todavía no arrancó.'], 422);
        }

        try {
            $partida = $servicio->siguienteRonda($sala);
        } catch (JugadaIlegalException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($servicio->respuestaParaJugador($sala->refresh(), $partida, $jugador->idDominio()));
    }

    /** Resuelve el jugador dueño del token para $sala, o un 401 si el token no le pertenece a nadie de esta sala. */
    private function autenticar(Request $request, Sala $sala): Jugador|JsonResponse
    {
        $token = $request->header('X-Jugador-Token') ?? $request->query('token');

        $jugador = $token !== null
            ? Jugador::where('sala_id', $sala->id)->where('token', $token)->first()
            : null;

        return $jugador ?? response()->json(['message' => 'Token inválido para esta sala.'], 401);
    }
}
