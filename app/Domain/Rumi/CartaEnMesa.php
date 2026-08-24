<?php

namespace App\Domain\Rumi;

final class CartaEnMesa
{
    public function __construct(
        public readonly Carta $carta,
        public readonly string $propietarioId,
    ) {
    }
}
