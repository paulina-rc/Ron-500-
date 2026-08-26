<?php

namespace App\Console\Commands\Simulacion;

use App\Domain\Rumi\Carta;
use App\Domain\Rumi\EstadoPartida;
use App\Domain\Rumi\Excepciones\JugadaIlegalException;
use App\Domain\Rumi\MotorRumi;
use App\Domain\Rumi\Numero;
use App\Domain\Rumi\Reglas;

/**
 * Bot simple que juega un turno completo llamando únicamente a la API pública
 * del motor (MotorRumi/Reglas). No conoce ni modifica el motor.
 *
 * Deliberadamente NO se reserva una carta "por las dudas" antes de bajar/embonar:
 * si bajar y embonar todo lo posible vacía la mano, eso queda expuesto tal cual
 * (ver elegirCartaParaBotar), en vez de que el bot lo evite en silencio.
 */
final class EstrategiaSimple
{
    public static function jugarTurno(EstadoPartida $estado): EstadoPartida
    {
        $jugadorId = $estado->turnoActual;

        $estado = self::intentar($estado, $jugadorId, 'comer', fn (EstadoPartida $e) => self::comer($e, $jugadorId));
        $estado = self::intentar($estado, $jugadorId, 'usarCartaObligatoria', fn (EstadoPartida $e) => self::usarCartaObligatoria($e, $jugadorId));
        $estado = self::intentar($estado, $jugadorId, 'bajarTodoLoQuePueda', fn (EstadoPartida $e) => self::bajarTodoLoQuePueda($e, $jugadorId));
        $estado = self::intentar($estado, $jugadorId, 'embonarTodoLoQuePueda', fn (EstadoPartida $e) => self::embonarTodoLoQuePueda($e, $jugadorId));

        $carta = self::elegirCartaParaBotar($estado, $jugadorId);
        if ($carta === null) {
            throw new AccionFallidaException(
                $estado,
                $jugadorId,
                'elegirCartaParaBotar',
                new \RuntimeException('El bot bajó/embonó toda su mano y no le queda ninguna carta para botar.')
            );
        }

        return self::intentar($estado, $jugadorId, 'botar', fn (EstadoPartida $e) => MotorRumi::botar($e, $jugadorId, $carta->id));
    }

    private static function intentar(EstadoPartida $estado, string $jugadorId, string $accion, callable $fn): EstadoPartida
    {
        try {
            return $fn($estado);
        } catch (\Throwable $e) {
            throw new AccionFallidaException($estado, $jugadorId, $accion, $e);
        }
    }

    /** Come de la basura empezando por la carta de más arriba (arrastra menos); si ninguna sirve, come del mazo. */
    private static function comer(EstadoPartida $estado, string $jugadorId): EstadoPartida
    {
        for ($i = count($estado->basura) - 1; $i >= 0; $i--) {
            try {
                return MotorRumi::comerDeLaBasura($estado, $jugadorId, $estado->basura[$i]->id);
            } catch (JugadaIlegalException) {
                // esa carta no tiene jugada legal, probar la siguiente hacia el fondo
            }
        }

        return MotorRumi::comerDelMazo($estado, $jugadorId);
    }

    /**
     * Coloca en mesa la carta que se acaba de comer de la basura, si quedó pendiente.
     * El motor ya garantizó (en comerDeLaBasura) que existe una jugada legal para ella;
     * si esta reconstrucción no la encuentra, es una discrepancia entre esa validación
     * y la realidad, y se deja reventar para que se reporte como bug.
     */
    private static function usarCartaObligatoria(EstadoPartida $estado, string $jugadorId): EstadoPartida
    {
        $obligatoriaId = $estado->cartaObligatoriaId;
        if ($obligatoriaId === null) {
            return $estado;
        }

        $jugador = $estado->jugadores[$jugadorId];
        $obligatoria = $jugador->buscarCarta($obligatoriaId);
        if ($obligatoria === null) {
            return $estado;
        }

        $mismoNumero = array_values(array_filter(
            $jugador->mano,
            fn (Carta $c) => $c->numero === $obligatoria->numero
        ));
        if (count($mismoNumero) >= Reglas::MIN_TRIO) {
            return MotorRumi::bajar($estado, $jugadorId, array_map(fn (Carta $c) => $c->id, $mismoNumero));
        }

        $corrida = self::construirEscaleraParaCarta($obligatoria, $jugador->mano);
        if ($corrida !== null) {
            return MotorRumi::bajar($estado, $jugadorId, array_map(fn (Carta $c) => $c->id, $corrida));
        }

        if ($jugador->seHaBajado) {
            foreach ($estado->mesa as $juego) {
                if (Reglas::puedeEmbonar($obligatoria, $juego)) {
                    return MotorRumi::embonar($estado, $jugadorId, $juego->id, $obligatoria->id);
                }
            }
        }

        throw new \RuntimeException(
            "La carta obligatoria {$obligatoria->id} no tiene jugada legal reconstruible, ".
            'pese a que el motor la validó al comer de la basura.'
        );
    }

    private static function bajarTodoLoQuePueda(EstadoPartida $estado, string $jugadorId): EstadoPartida
    {
        while (true) {
            $mano = $estado->jugadores[$jugadorId]->mano;

            $grupo = self::mejorTrioEnMano($mano) ?? self::mejorEscaleraEnMano($mano);
            if ($grupo === null) {
                return $estado;
            }

            $estado = MotorRumi::bajar($estado, $jugadorId, array_map(fn (Carta $c) => $c->id, $grupo));
        }
    }

    private static function embonarTodoLoQuePueda(EstadoPartida $estado, string $jugadorId): EstadoPartida
    {
        if (! $estado->jugadores[$jugadorId]->seHaBajado) {
            return $estado;
        }

        while (true) {
            $hizoAlgo = false;

            foreach ($estado->jugadores[$jugadorId]->mano as $carta) {
                foreach ($estado->mesa as $juego) {
                    if (Reglas::puedeEmbonar($carta, $juego)) {
                        $estado = MotorRumi::embonar($estado, $jugadorId, $juego->id, $carta->id);
                        $hizoAlgo = true;
                        break 2;
                    }
                }
            }

            if (! $hizoAlgo) {
                return $estado;
            }
        }
    }

    /** La carta que no sirve para ninguna jugada posible; a igualdad, la de más puntos. Null si no queda ninguna. */
    private static function elegirCartaParaBotar(EstadoPartida $estado, string $jugadorId): ?Carta
    {
        $mano = $estado->jugadores[$jugadorId]->mano;
        if (count($mano) === 0) {
            return null;
        }

        $inutiles = array_values(array_filter(
            $mano,
            fn (Carta $c) => ! Reglas::existeJugadaLegalParaCarta($c, $mano, $estado, $jugadorId)
        ));

        $candidatas = count($inutiles) > 0 ? $inutiles : $mano;
        usort($candidatas, fn (Carta $a, Carta $b) => $b->puntos() <=> $a->puntos());

        return $candidatas[0];
    }

    /** @param Carta[] $mano @return Carta[]|null El grupo del número con más copias (>=3), o null. */
    private static function mejorTrioEnMano(array $mano): ?array
    {
        $porNumero = [];
        foreach ($mano as $carta) {
            $porNumero[$carta->numero][] = $carta;
        }

        $mejor = null;
        foreach ($porNumero as $grupo) {
            if (count($grupo) >= Reglas::MIN_TRIO && ($mejor === null || count($grupo) > count($mejor))) {
                $mejor = $grupo;
            }
        }

        return $mejor;
    }

    /** @param Carta[] $mano @return Carta[]|null La corrida más larga disponible en algún palo, o null. */
    private static function mejorEscaleraEnMano(array $mano): ?array
    {
        $porPalo = [];
        foreach ($mano as $carta) {
            $porPalo[$carta->palo][] = $carta;
        }

        $mejor = null;
        foreach ($porPalo as $cartasDelPalo) {
            $corrida = self::corridaMasLargaEnPalo($cartasDelPalo);
            if ($corrida !== null && ($mejor === null || count($corrida) > count($mejor))) {
                $mejor = $corrida;
            }
        }

        return $mejor;
    }

    /**
     * La corrida (consecutiva, >=3) más larga formable con estas cartas de un mismo palo,
     * probando cada as disponible como 1 o como 14.
     *
     * @param Carta[] $cartasDelPalo
     * @return Carta[]|null
     */
    private static function corridaMasLargaEnPalo(array $cartasDelPalo): ?array
    {
        $ases = array_values(array_filter($cartasDelPalo, fn (Carta $c) => $c->esAs()));
        $fijasPorRango = [];
        foreach ($cartasDelPalo as $carta) {
            if (! $carta->esAs()) {
                $rango = Numero::rangosPosibles($carta->numero)[0];
                $fijasPorRango[$rango] ??= $carta;
            }
        }

        $numAses = count($ases);
        $mejor = null;

        for ($mascara = 0; $mascara < (1 << $numAses); $mascara++) {
            $porRango = $fijasPorRango;
            for ($i = 0; $i < $numAses; $i++) {
                $rango = ($mascara >> $i & 1) ? 14 : 1;
                $porRango[$rango] ??= $ases[$i];
            }

            $corrida = self::corridaMasLargaEnMapa($porRango);
            if ($corrida !== null && ($mejor === null || count($corrida) > count($mejor))) {
                $mejor = $corrida;
            }
        }

        return $mejor !== null && count($mejor) >= Reglas::MIN_ESCALERA ? $mejor : null;
    }

    /**
     * @param array<int, Carta> $porRango
     * @return Carta[]|null
     */
    private static function corridaMasLargaEnMapa(array $porRango): ?array
    {
        $rangos = array_keys($porRango);
        sort($rangos);

        $mejorRangos = [];
        $actual = [];
        $anterior = null;

        foreach ($rangos as $rango) {
            $actual = ($anterior !== null && $rango === $anterior + 1) ? [...$actual, $rango] : [$rango];
            if (count($actual) > count($mejorRangos)) {
                $mejorRangos = $actual;
            }
            $anterior = $rango;
        }

        if (count($mejorRangos) < Reglas::MIN_ESCALERA) {
            return null;
        }

        return array_map(fn (int $r) => $porRango[$r], $mejorRangos);
    }

    /**
     * Reconstruye una corrida concreta que incluya a $obligatoria, con la misma lógica
     * de asignación de ases que Reglas::existeCorridaParaCarta (que solo confirma que existe).
     *
     * @param Carta[] $manoDisponible
     * @return Carta[]|null
     */
    private static function construirEscaleraParaCarta(Carta $obligatoria, array $manoDisponible): ?array
    {
        $mismoPalo = array_values(array_filter(
            $manoDisponible,
            fn (Carta $c) => $c->palo === $obligatoria->palo && ! $c->esIgualA($obligatoria)
        ));

        $ases = array_values(array_filter($mismoPalo, fn (Carta $c) => $c->esAs()));
        $fijasPorRango = [];
        foreach ($mismoPalo as $c) {
            if (! $c->esAs()) {
                $rango = Numero::rangosPosibles($c->numero)[0];
                $fijasPorRango[$rango] ??= $c;
            }
        }

        $numAses = count($ases);

        foreach ($obligatoria->rangosPosibles() as $t) {
            for ($mascara = 0; $mascara < (1 << $numAses); $mascara++) {
                $porRango = $fijasPorRango;
                $porRango[$t] = $obligatoria;
                for ($i = 0; $i < $numAses; $i++) {
                    $rango = ($mascara >> $i & 1) ? 14 : 1;
                    $porRango[$rango] ??= $ases[$i];
                }

                $izq = $t;
                while (isset($porRango[$izq - 1])) {
                    $izq--;
                }
                $der = $t;
                while (isset($porRango[$der + 1])) {
                    $der++;
                }

                if ($der - $izq + 1 >= Reglas::MIN_ESCALERA) {
                    $grupo = [];
                    for ($r = $izq; $r <= $der; $r++) {
                        $grupo[] = $porRango[$r];
                    }

                    return $grupo;
                }
            }
        }

        return null;
    }
}
