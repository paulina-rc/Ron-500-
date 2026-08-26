<?php

namespace App\Domain\Rumi;

final class CartaEnMesa
{
    public function __construct(
        public readonly Carta $carta,
        public readonly string $propietarioId,
    ) {
    }

    /** @return array{carta: array, propietarioId: string} */
    public function aArray(): array
    {
        return [
            'carta' => $this->carta->aArray(),
            'propietarioId' => $this->propietarioId,
        ];
    }

    public static function desdeArray(array $datos): self
    {
        return new self(Carta::desdeArray($datos['carta']), $datos['propietarioId']);
    }
}
