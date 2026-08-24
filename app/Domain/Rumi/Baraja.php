<?php

namespace App\Domain\Rumi;

final class Baraja
{
    /** @return Carta[] Las 104 cartas de las dos barajas, sin barajar. Sin comodines. */
    public static function crearCompleta(): array
    {
        $cartas = [];
        foreach ([1, 2] as $copia) {
            foreach (Palo::TODOS as $palo) {
                foreach (Numero::TODOS as $numero) {
                    $cartas[] = new Carta($numero, $palo, $copia);
                }
            }
        }

        return $cartas;
    }

    /**
     * @param Carta[] $cartas
     * @return Carta[]
     */
    public static function barajar(array $cartas, ?int $semilla = null): array
    {
        if ($semilla !== null) {
            mt_srand($semilla);
        }

        for ($i = count($cartas) - 1; $i > 0; $i--) {
            $j = $semilla !== null ? mt_rand(0, $i) : random_int(0, $i);
            [$cartas[$i], $cartas[$j]] = [$cartas[$j], $cartas[$i]];
        }

        return $cartas;
    }
}
