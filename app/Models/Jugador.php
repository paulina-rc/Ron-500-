<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class Jugador extends Model
{
    private const INTENTOS_TOKEN = 20;

    // Eloquent pluraliza en inglés: "Jugador" -> "jugadors". Hay que decirle la tabla real.
    protected $table = 'jugadores';

    protected $fillable = ['sala_id', 'nombre', 'token', 'puesto'];

    /** El token es la prueba de identidad del jugador al reconectar: nunca se le manda al rival. */
    protected $hidden = ['token'];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    /**
     * El id que este jugador usa dentro del motor (App\Domain\Rumi\EstadoPartida).
     * Con prefijo a propósito: el motor usa ese id como clave de array, y PHP
     * convierte claves que parecen enteros ("1", "2") a int silenciosamente, lo
     * que rompe comparaciones estrictas en otras partes del motor (p. ej.
     * EstadoPartida::siguienteJugador). Prefijarlo evita pisar ese caso sin
     * tocar App\Domain\Rumi.
     */
    public function idDominio(): string
    {
        return 'j'.$this->id;
    }

    /** Crea un jugador nuevo en $sala con un token largo y único, reintentando ante colisión. */
    public static function crear(Sala $sala, string $nombre, int $puesto): self
    {
        for ($intento = 0; $intento < self::INTENTOS_TOKEN; $intento++) {
            try {
                return self::create([
                    'sala_id' => $sala->id,
                    'nombre' => $nombre,
                    'token' => Str::random(64),
                    'puesto' => $puesto,
                ]);
            } catch (QueryException) {
                // token duplicado (constraint unique de la tabla): reintentar con uno nuevo
            }
        }

        throw new \RuntimeException('No se pudo generar un token de jugador único.');
    }
}
