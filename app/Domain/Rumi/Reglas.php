<?php

namespace App\Domain\Rumi;

final class Reglas
{
    public const MIN_TRIO = 3;

    public const MAX_TRIO = 8; // tope natural: ocho cartas de cada número entre las dos barajas

    public const MIN_ESCALERA = 3;

    public const MAX_ESCALERA = 13; // A a K

    public const PUNTOS_GANE = 20; // sección 2: quien cierra la ronda

    public const META_PARTIDA = 500; // sección 1

    /**
     * Sección 1: gana el primero que llegue a 500. Si en la misma ronda varios
     * pasan de 500, gana el que tenga más. Si los que van primeros empatan
     * exacto, no hay ganador todavía y se juega otra ronda.
     *
     * @param array<string, int> $totales puntos acumulados por jugador, al cierre de una ronda
     */
    public static function ganadorDeLaPartida(array $totales): ?string
    {
        if ($totales === []) {
            return null;
        }

        $maximo = max($totales);
        if ($maximo < self::META_PARTIDA) {
            return null;
        }

        $lideres = array_keys($totales, $maximo, true);
        if (count($lideres) !== 1) {
            return null;
        }

        return (string) $lideres[0];
    }

    /** @param Carta[] $cartas */
    public static function esTrioValido(array $cartas): bool
    {
        $n = count($cartas);
        if ($n < self::MIN_TRIO || $n > self::MAX_TRIO) {
            return false;
        }

        $numero = $cartas[0]->numero;
        foreach ($cartas as $carta) {
            if ($carta->numero !== $numero) {
                return false;
            }
        }

        return true;
    }

    /** @param Carta[] $cartas */
    public static function esEscaleraValida(array $cartas): bool
    {
        return self::resolverRangoEscalera($cartas) !== null;
    }

    /**
     * Intenta resolver un grupo de cartas como escalera: mismo palo, en secuencia,
     * decidiendo el valor del As (1 o 14) si viene en el grupo. La escalera no da
     * la vuelta: K-A-2 no tiene resolución válida.
     *
     * @param Carta[] $cartas
     * @return array{0:int,1:int}|null [rango mínimo, rango máximo], o null si no es válida.
     */
    public static function resolverRangoEscalera(array $cartas): ?array
    {
        $n = count($cartas);
        if ($n < self::MIN_ESCALERA || $n > self::MAX_ESCALERA) {
            return null;
        }

        $palo = $cartas[0]->palo;
        foreach ($cartas as $carta) {
            if ($carta->palo !== $palo) {
                return null;
            }
        }

        $ases = array_values(array_filter($cartas, fn (Carta $c) => $c->esAs()));
        if (count($ases) > 1) {
            return null; // dos ases del mismo palo no pueden convivir en una secuencia
        }

        $fijas = array_values(array_map(
            fn (Carta $c) => Numero::rangosPosibles($c->numero)[0],
            array_values(array_filter($cartas, fn (Carta $c) => ! $c->esAs()))
        ));

        if (count($fijas) !== count(array_unique($fijas))) {
            return null; // número repetido, no puede ser una secuencia
        }

        if (count($ases) === 0) {
            sort($fijas);
            $min = $fijas[0];
            $max = $fijas[count($fijas) - 1];

            return ($max - $min + 1 === $n) ? [$min, $max] : null;
        }

        foreach ([1, 14] as $valorAs) {
            if (in_array($valorAs, $fijas, true)) {
                continue;
            }
            $rangos = $fijas;
            $rangos[] = $valorAs;
            sort($rangos);
            $min = $rangos[0];
            $max = $rangos[count($rangos) - 1];
            if ($max - $min + 1 === $n) {
                return [$min, $max];
            }
        }

        return null;
    }

    /**
     * ¿Existe una corrida de al menos MIN_ESCALERA cartas del mismo palo que incluya
     * a $carta, usando cartas disponibles en $disponibles (sin contar a $carta misma)?
     *
     * @param Carta[] $disponibles
     */
    public static function existeCorridaParaCarta(Carta $carta, array $disponibles): bool
    {
        $mismoPalo = array_values(array_filter(
            $disponibles,
            fn (Carta $c) => $c->palo === $carta->palo && ! $c->esIgualA($carta)
        ));

        $ases = array_values(array_filter($mismoPalo, fn (Carta $c) => $c->esAs()));
        $fijas = array_values(array_unique(array_map(
            fn (Carta $c) => Numero::rangosPosibles($c->numero)[0],
            array_values(array_filter($mismoPalo, fn (Carta $c) => ! $c->esAs()))
        )));

        $numAses = count($ases);
        foreach ($carta->rangosPosibles() as $t) {
            for ($mascara = 0; $mascara < (1 << $numAses); $mascara++) {
                $logrados = array_fill_keys($fijas, true);
                $logrados[$t] = true;
                for ($i = 0; $i < $numAses; $i++) {
                    $logrados[($mascara >> $i & 1) ? 14 : 1] = true;
                }

                $izq = $t;
                while (isset($logrados[$izq - 1])) {
                    $izq--;
                }
                $der = $t;
                while (isset($logrados[$der + 1])) {
                    $der++;
                }

                if ($der - $izq + 1 >= self::MIN_ESCALERA) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * ¿Existe un trío de al menos MIN_TRIO cartas del mismo número (el palo no
     * importa) que incluya a $carta, usando cartas disponibles en $disponibles?
     *
     * @param Carta[] $disponibles
     */
    public static function existeTrioParaCarta(Carta $carta, array $disponibles): bool
    {
        $mismoNumero = array_filter(
            $disponibles,
            fn (Carta $c) => $c->numero === $carta->numero && ! $c->esIgualA($carta)
        );

        return count($mismoNumero) + 1 >= self::MIN_TRIO;
    }

    public static function puedeEmbonar(Carta $carta, JuegoMesa $juego): bool
    {
        $cartasJuego = $juego->soloCartas();

        if ($juego->tipo === JuegoMesa::TRIO) {
            if (count($cartasJuego) >= self::MAX_TRIO) {
                return false;
            }

            return $cartasJuego[0]->numero === $carta->numero;
        }

        // escalera
        if ($carta->palo !== $juego->palo()) {
            return false;
        }
        if (count($cartasJuego) >= self::MAX_ESCALERA) {
            return false;
        }

        [$min, $max] = $juego->rangoEscalera();
        foreach ($carta->rangosPosibles() as $rango) {
            if ($rango === $min - 1 || $rango === $max + 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * ¿Existe alguna jugada legal (nuevo juego o embone) para colocar $carta en mesa,
     * usando $manoDisponible como material y el estado de la partida para saber si
     * $jugadorId ya se bajó y qué hay en la mesa, tal que además quede al menos una
     * carta libre para botar? Bajar/embonar nunca pueden vaciar la mano (hay que
     * botar siempre para cerrar el turno), así que una jugada que sí existe pero
     * que obligaría a usar TODO el material disponible no cuenta como legal acá.
     *
     * Un trío o una escalera nuevos siempre se pueden armar con el mínimo de 3
     * cartas (el resto del grupo es opcional), así que alcanza con exigir que
     * $manoDisponible tenga más de 3 cartas en total. Embonar solo consume la
     * carta misma, así que nunca vacía la mano por su cuenta.
     *
     * @param Carta[] $manoDisponible
     */
    public static function existeJugadaLegalParaCarta(
        Carta $carta,
        array $manoDisponible,
        EstadoPartida $estado,
        string $jugadorId
    ): bool {
        $sobraCartaParaBotar = count($manoDisponible) > self::MIN_TRIO;

        if ($sobraCartaParaBotar && self::existeTrioParaCarta($carta, $manoDisponible)) {
            return true;
        }
        if ($sobraCartaParaBotar && self::existeCorridaParaCarta($carta, $manoDisponible)) {
            return true;
        }

        $jugador = $estado->jugadores[$jugadorId];
        if ($jugador->seHaBajado) {
            foreach ($estado->mesa as $juego) {
                if (self::puedeEmbonar($carta, $juego)) {
                    return true;
                }
            }
        }

        return false;
    }
}
