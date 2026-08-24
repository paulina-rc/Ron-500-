<?php

namespace App\Domain\Rumi;

final class Numero
{
    public const TODOS = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];

    private const VALOR_POR_NUMERO = [
        'A' => 15,
        '2' => 20,
        '3' => 5, '4' => 5, '5' => 5, '6' => 5, '7' => 5, '8' => 5, '9' => 5,
        '10' => 10, 'J' => 10, 'Q' => 10, 'K' => 10,
    ];

    private const RANGO_BASE = [
        '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
        '10' => 10, 'J' => 11, 'Q' => 12, 'K' => 13,
    ];

    public static function puntos(string $numero): int
    {
        return self::VALOR_POR_NUMERO[$numero]
            ?? throw new \InvalidArgumentException("Número de carta inválido: {$numero}");
    }

    /**
     * Los rangos posibles del número dentro de una escalera. El As puede ser 1 o 14,
     * pero nunca las dos cosas en la misma escalera (eso se valida aparte).
     *
     * @return int[]
     */
    public static function rangosPosibles(string $numero): array
    {
        if ($numero === 'A') {
            return [1, 14];
        }

        return [
            self::RANGO_BASE[$numero]
                ?? throw new \InvalidArgumentException("Número de carta inválido: {$numero}"),
        ];
    }
}
