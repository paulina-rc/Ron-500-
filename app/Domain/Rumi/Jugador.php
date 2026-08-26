<?php

namespace App\Domain\Rumi;

final class Jugador
{
    /** @param Carta[] $mano */
    public function __construct(
        public readonly string $id,
        public array $mano,
        public bool $seHaBajado = false,
    ) {
    }

    public function clonar(): self
    {
        return new self($this->id, $this->mano, $this->seHaBajado);
    }

    public function tieneCarta(string $cartaId): bool
    {
        return $this->buscarCarta($cartaId) !== null;
    }

    public function buscarCarta(string $cartaId): ?Carta
    {
        foreach ($this->mano as $carta) {
            if ($carta->id === $cartaId) {
                return $carta;
            }
        }

        return null;
    }

    public function quitarCarta(string $cartaId): void
    {
        foreach ($this->mano as $i => $carta) {
            if ($carta->id === $cartaId) {
                array_splice($this->mano, $i, 1);

                return;
            }
        }
    }

    public function agregarCarta(Carta $carta): void
    {
        $this->mano[] = $carta;
    }

    public function puntosEnMano(): int
    {
        return array_sum(array_map(fn (Carta $c) => $c->puntos(), $this->mano));
    }

    /** @return array{id: string, mano: array, seHaBajado: bool} */
    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'mano' => array_values(array_map(fn (Carta $c) => $c->aArray(), $this->mano)),
            'seHaBajado' => $this->seHaBajado,
        ];
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            $datos['id'],
            array_map(fn (array $c) => Carta::desdeArray($c), $datos['mano']),
            $datos['seHaBajado'],
        );
    }
}
