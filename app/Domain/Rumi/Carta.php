<?php

namespace App\Domain\Rumi;

final class Carta
{
    public readonly string $id;

    public function __construct(
        public readonly string $numero,
        public readonly string $palo,
        public readonly int $copia,
    ) {
        if (! in_array($numero, Numero::TODOS, true)) {
            throw new \InvalidArgumentException("Número de carta inválido: {$numero}");
        }
        if (! in_array($palo, Palo::TODOS, true)) {
            throw new \InvalidArgumentException("Palo de carta inválido: {$palo}");
        }
        if ($copia !== 1 && $copia !== 2) {
            throw new \InvalidArgumentException("Copia de carta inválida: {$copia}");
        }

        $this->id = "{$numero}{$palo}#{$copia}";
    }

    public function puntos(): int
    {
        return Numero::puntos($this->numero);
    }

    /** @return int[] */
    public function rangosPosibles(): array
    {
        return Numero::rangosPosibles($this->numero);
    }

    public function esAs(): bool
    {
        return $this->numero === 'A';
    }

    public function esIgualA(Carta $otra): bool
    {
        return $this->id === $otra->id;
    }

    /** @return array{numero: string, palo: string, copia: int} */
    public function aArray(): array
    {
        return [
            'numero' => $this->numero,
            'palo' => $this->palo,
            'copia' => $this->copia,
        ];
    }

    public static function desdeArray(array $datos): self
    {
        return new self($datos['numero'], $datos['palo'], $datos['copia']);
    }
}
