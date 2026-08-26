<?php

namespace App\Domain\Rumi;

final class JuegoMesa
{
    public const TRIO = 'trio';

    public const ESCALERA = 'escalera';

    /** @param CartaEnMesa[] $cartas */
    public function __construct(
        public readonly string $id,
        public readonly string $tipo,
        public array $cartas,
    ) {
    }

    public function clonar(): self
    {
        return new self($this->id, $this->tipo, $this->cartas);
    }

    /** @return Carta[] */
    public function soloCartas(): array
    {
        return array_map(fn (CartaEnMesa $c) => $c->carta, $this->cartas);
    }

    public function palo(): string
    {
        return $this->cartas[0]->carta->palo;
    }

    /** @return array{0:int,1:int} */
    public function rangoEscalera(): array
    {
        $rango = Reglas::resolverRangoEscalera($this->soloCartas());
        if ($rango === null) {
            throw new \LogicException('El juego en mesa no es una escalera válida.');
        }

        return $rango;
    }

    /** @return array{id: string, tipo: string, cartas: array} */
    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'cartas' => array_values(array_map(fn (CartaEnMesa $c) => $c->aArray(), $this->cartas)),
        ];
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            $datos['id'],
            $datos['tipo'],
            array_map(fn (array $c) => CartaEnMesa::desdeArray($c), $datos['cartas']),
        );
    }
}
