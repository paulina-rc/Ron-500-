<?php

namespace App\Domain\Rumi;

final class EstadoPartida
{
    public const DEBE_COMER = 'debe_comer';

    public const PUEDE_JUGAR = 'puede_jugar';

    /**
     * @param array<string, Jugador> $jugadores
     * @param Carta[] $mazo
     * @param Carta[] $basura Pila de descarte; el final del arreglo es la carta de encima (la más reciente).
     * @param array<string, JuegoMesa> $mesa
     */
    public function __construct(
        public array $jugadores,
        public array $mazo,
        public array $basura,
        public array $mesa,
        public string $turnoActual,
        public string $fase = self::DEBE_COMER,
        public ?string $cartaObligatoriaId = null,
        public bool $rondaTerminada = false,
        public ?string $ganadorRonda = null,
    ) {
    }

    /** @param string[] $idsJugadores */
    public static function iniciar(array $idsJugadores, ?int $semilla = null): self
    {
        if (count($idsJugadores) < 2) {
            throw new \InvalidArgumentException('Se necesitan al menos dos jugadores.');
        }

        $mazo = Baraja::barajar(Baraja::crearCompleta(), $semilla);

        $jugadores = [];
        foreach ($idsJugadores as $id) {
            $mano = [];
            for ($i = 0; $i < 12; $i++) {
                $mano[] = array_pop($mazo);
            }
            $jugadores[$id] = new Jugador($id, $mano);
        }

        $basura = [array_pop($mazo)];

        return new self(
            jugadores: $jugadores,
            mazo: $mazo,
            basura: $basura,
            mesa: [],
            turnoActual: $idsJugadores[0],
        );
    }

    public function clonar(): self
    {
        $jugadores = [];
        foreach ($this->jugadores as $id => $jugador) {
            $jugadores[$id] = $jugador->clonar();
        }

        $mesa = [];
        foreach ($this->mesa as $id => $juego) {
            $mesa[$id] = $juego->clonar();
        }

        return new self(
            jugadores: $jugadores,
            mazo: $this->mazo,
            basura: $this->basura,
            mesa: $mesa,
            turnoActual: $this->turnoActual,
            fase: $this->fase,
            cartaObligatoriaId: $this->cartaObligatoriaId,
            rondaTerminada: $this->rondaTerminada,
            ganadorRonda: $this->ganadorRonda,
        );
    }

    public function jugadorActual(): Jugador
    {
        return $this->jugadores[$this->turnoActual];
    }

    /** @return string[] */
    public function idsJugadores(): array
    {
        return array_keys($this->jugadores);
    }

    public function siguienteJugador(string $actual): string
    {
        $ids = $this->idsJugadores();
        $i = array_search($actual, $ids, true);

        return $ids[($i + 1) % count($ids)];
    }

    /** Arreglo plano (solo tipos escalares/arreglos) listo para json_encode. */
    public function aArray(): array
    {
        $jugadores = [];
        foreach ($this->jugadores as $id => $jugador) {
            $jugadores[$id] = $jugador->aArray();
        }

        $mesa = [];
        foreach ($this->mesa as $id => $juego) {
            $mesa[$id] = $juego->aArray();
        }

        return [
            'jugadores' => $jugadores,
            'mazo' => array_values(array_map(fn (Carta $c) => $c->aArray(), $this->mazo)),
            'basura' => array_values(array_map(fn (Carta $c) => $c->aArray(), $this->basura)),
            'mesa' => $mesa,
            'turnoActual' => $this->turnoActual,
            'fase' => $this->fase,
            'cartaObligatoriaId' => $this->cartaObligatoriaId,
            'rondaTerminada' => $this->rondaTerminada,
            'ganadorRonda' => $this->ganadorRonda,
        ];
    }

    /** Reconstruye el estado a partir del arreglo producido por aArray(). */
    public static function desdeArray(array $datos): self
    {
        $jugadores = [];
        foreach ($datos['jugadores'] as $id => $jugador) {
            $jugadores[$id] = Jugador::desdeArray($jugador);
        }

        $mesa = [];
        foreach ($datos['mesa'] as $id => $juego) {
            $mesa[$id] = JuegoMesa::desdeArray($juego);
        }

        return new self(
            jugadores: $jugadores,
            mazo: array_map(fn (array $c) => Carta::desdeArray($c), $datos['mazo']),
            basura: array_map(fn (array $c) => Carta::desdeArray($c), $datos['basura']),
            mesa: $mesa,
            turnoActual: $datos['turnoActual'],
            fase: $datos['fase'],
            cartaObligatoriaId: $datos['cartaObligatoriaId'],
            rondaTerminada: $datos['rondaTerminada'],
            ganadorRonda: $datos['ganadorRonda'],
        );
    }
}
