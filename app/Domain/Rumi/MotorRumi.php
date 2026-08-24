<?php

namespace App\Domain\Rumi;

use App\Domain\Rumi\Excepciones\JugadaIlegalException;

/**
 * Motor de reglas de Rumi Quinientos. Cada acción recibe un EstadoPartida y
 * devuelve un EstadoPartida nuevo, o lanza JugadaIlegalException. No conoce
 * base de datos ni peticiones HTTP.
 */
final class MotorRumi
{
    public static function comerDelMazo(EstadoPartida $estado, string $jugadorId): EstadoPartida
    {
        self::validarTurno($estado, $jugadorId);
        self::validarFase($estado, EstadoPartida::DEBE_COMER, 'Ya comiste este turno.');

        if (count($estado->mazo) === 0) {
            throw new JugadaIlegalException('No quedan cartas en el mazo.');
        }

        $nuevo = $estado->clonar();
        $carta = array_pop($nuevo->mazo);
        $nuevo->jugadores[$jugadorId]->agregarCarta($carta);
        $nuevo->fase = EstadoPartida::PUEDE_JUGAR;

        return $nuevo;
    }

    public static function comerDeLaBasura(EstadoPartida $estado, string $jugadorId, string $cartaObjetivoId): EstadoPartida
    {
        self::validarTurno($estado, $jugadorId);
        self::validarFase($estado, EstadoPartida::DEBE_COMER, 'Ya comiste este turno.');

        $indice = self::buscarIndiceEnBasura($estado, $cartaObjetivoId);
        if ($indice === null) {
            throw new JugadaIlegalException('Esa carta no está en la basura.');
        }

        $cartaObjetivo = $estado->basura[$indice];
        $cartasEncima = array_slice($estado->basura, $indice + 1);

        $jugador = $estado->jugadores[$jugadorId];
        $manoCandidata = array_merge($jugador->mano, $cartasEncima, [$cartaObjetivo]);

        if (! Reglas::existeJugadaLegalParaCarta($cartaObjetivo, $manoCandidata, $estado, $jugadorId)) {
            throw new JugadaIlegalException(
                'No podés comer esa carta: no tenés una jugada legal para colocarla en mesa.'
            );
        }

        $nuevo = $estado->clonar();
        $nuevo->basura = array_slice($nuevo->basura, 0, $indice);

        foreach (array_merge($cartasEncima, [$cartaObjetivo]) as $carta) {
            $nuevo->jugadores[$jugadorId]->agregarCarta($carta);
        }

        $nuevo->cartaObligatoriaId = $cartaObjetivo->id;
        $nuevo->fase = EstadoPartida::PUEDE_JUGAR;

        return $nuevo;
    }

    /** @param string[] $cartaIds */
    public static function bajar(EstadoPartida $estado, string $jugadorId, array $cartaIds): EstadoPartida
    {
        self::validarTurno($estado, $jugadorId);
        self::validarFase($estado, EstadoPartida::PUEDE_JUGAR, 'Tenés que comer antes de bajar.');

        $jugador = $estado->jugadores[$jugadorId];
        $cartas = self::resolverCartasDeMano($jugador, $cartaIds);

        if (Reglas::esTrioValido($cartas)) {
            $tipo = JuegoMesa::TRIO;
        } elseif (Reglas::esEscaleraValida($cartas)) {
            $tipo = JuegoMesa::ESCALERA;
        } else {
            throw new JugadaIlegalException('Esa jugada no es válida: no forma un trío ni una escalera.');
        }

        $nuevo = $estado->clonar();
        foreach ($cartaIds as $id) {
            $nuevo->jugadores[$jugadorId]->quitarCarta($id);
        }

        $juegoId = 'j'.(count($nuevo->mesa) + 1);
        $cartasEnMesa = array_map(fn (Carta $c) => new CartaEnMesa($c, $jugadorId), $cartas);

        $nuevo->mesa[$juegoId] = new JuegoMesa($juegoId, $tipo, $cartasEnMesa);
        $nuevo->jugadores[$jugadorId]->seHaBajado = true;

        return $nuevo;
    }

    public static function embonar(EstadoPartida $estado, string $jugadorId, string $juegoId, string $cartaId): EstadoPartida
    {
        self::validarTurno($estado, $jugadorId);
        self::validarFase($estado, EstadoPartida::PUEDE_JUGAR, 'Tenés que comer antes de embonar.');

        $jugador = $estado->jugadores[$jugadorId];
        if (! $jugador->seHaBajado) {
            throw new JugadaIlegalException('Tenés que bajar tu primera jugada antes de poder embonar.');
        }

        if (! isset($estado->mesa[$juegoId])) {
            throw new JugadaIlegalException('Ese juego no existe en la mesa.');
        }

        $carta = $jugador->buscarCarta($cartaId);
        if ($carta === null) {
            throw new JugadaIlegalException('No tenés esa carta en la mano.');
        }

        $juego = $estado->mesa[$juegoId];
        if (! Reglas::puedeEmbonar($carta, $juego)) {
            throw new JugadaIlegalException('Esa carta no embona en ese juego.');
        }

        $nuevo = $estado->clonar();
        $nuevo->jugadores[$jugadorId]->quitarCarta($cartaId);

        $juegoNuevo = $nuevo->mesa[$juegoId];
        if ($juego->tipo === JuegoMesa::ESCALERA) {
            [$min] = $juego->rangoEscalera();
            $extiendeAbajo = in_array($min - 1, $carta->rangosPosibles(), true);
            if ($extiendeAbajo) {
                array_unshift($juegoNuevo->cartas, new CartaEnMesa($carta, $jugadorId));
            } else {
                $juegoNuevo->cartas[] = new CartaEnMesa($carta, $jugadorId);
            }
        } else {
            $juegoNuevo->cartas[] = new CartaEnMesa($carta, $jugadorId);
        }

        return $nuevo;
    }

    public static function botar(EstadoPartida $estado, string $jugadorId, string $cartaId): EstadoPartida
    {
        self::validarTurno($estado, $jugadorId);
        self::validarFase($estado, EstadoPartida::PUEDE_JUGAR, 'Tenés que comer antes de botar.');

        $jugador = $estado->jugadores[$jugadorId];
        $carta = $jugador->buscarCarta($cartaId);
        if ($carta === null) {
            throw new JugadaIlegalException('No tenés esa carta en la mano.');
        }

        if ($estado->cartaObligatoriaId !== null) {
            if ($estado->cartaObligatoriaId === $cartaId) {
                throw new JugadaIlegalException(
                    'Tenés que usar en mesa la carta que comiste de la basura, no podés botarla.'
                );
            }
            if ($jugador->tieneCarta($estado->cartaObligatoriaId)) {
                throw new JugadaIlegalException(
                    'Tenés que usar la carta que comiste de la basura antes de botar.'
                );
            }
        }

        $nuevo = $estado->clonar();
        $nuevo->jugadores[$jugadorId]->quitarCarta($cartaId);
        $nuevo->basura[] = $carta;
        $nuevo->cartaObligatoriaId = null;

        if (count($nuevo->jugadores[$jugadorId]->mano) === 0) {
            $nuevo->rondaTerminada = true;
            $nuevo->ganadorRonda = $jugadorId;
        } else {
            $nuevo->turnoActual = $nuevo->siguienteJugador($jugadorId);
            $nuevo->fase = EstadoPartida::DEBE_COMER;

            if (count($nuevo->mazo) === 0) {
                $nuevo->rondaTerminada = true;
                $nuevo->ganadorRonda = null;
            }
        }

        return $nuevo;
    }

    /** Puntos de la ronda para $jugadorId: mesa suma (por dueño de cada carta) + gane - mano. */
    public static function puntosDeRonda(EstadoPartida $estado, string $jugadorId): int
    {
        $puntosMesa = 0;
        foreach ($estado->mesa as $juego) {
            foreach ($juego->cartas as $cartaEnMesa) {
                if ($cartaEnMesa->propietarioId === $jugadorId) {
                    $puntosMesa += $cartaEnMesa->carta->puntos();
                }
            }
        }

        $gane = $estado->ganadorRonda === $jugadorId ? 20 : 0;
        $puntosMano = $estado->jugadores[$jugadorId]->puntosEnMano();

        return $puntosMesa + $gane - $puntosMano;
    }

    private static function validarTurno(EstadoPartida $estado, string $jugadorId): void
    {
        if (! isset($estado->jugadores[$jugadorId])) {
            throw new JugadaIlegalException('Ese jugador no existe en la partida.');
        }
        if ($estado->rondaTerminada) {
            throw new JugadaIlegalException('La ronda ya terminó.');
        }
        if ($estado->turnoActual !== $jugadorId) {
            throw new JugadaIlegalException('No es tu turno.');
        }
    }

    private static function validarFase(EstadoPartida $estado, string $faseEsperada, string $mensaje): void
    {
        if ($estado->fase !== $faseEsperada) {
            throw new JugadaIlegalException($mensaje);
        }
    }

    /**
     * @param string[] $cartaIds
     * @return Carta[]
     */
    private static function resolverCartasDeMano(Jugador $jugador, array $cartaIds): array
    {
        if (count($cartaIds) !== count(array_unique($cartaIds))) {
            throw new JugadaIlegalException('No podés repetir la misma carta en una jugada.');
        }

        $cartas = [];
        foreach ($cartaIds as $id) {
            $carta = $jugador->buscarCarta($id);
            if ($carta === null) {
                throw new JugadaIlegalException('No tenés esa carta en la mano.');
            }
            $cartas[] = $carta;
        }

        return $cartas;
    }

    private static function buscarIndiceEnBasura(EstadoPartida $estado, string $cartaId): ?int
    {
        foreach ($estado->basura as $i => $carta) {
            if ($carta->id === $cartaId) {
                return $i;
            }
        }

        return null;
    }
}
