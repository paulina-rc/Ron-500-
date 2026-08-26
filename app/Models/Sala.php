<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;

class Sala extends Model
{
    public const ESPERANDO = 'esperando';

    public const JUGANDO = 'jugando';

    public const TERMINADA = 'terminada';

    private const INTENTOS_CODIGO = 20;

    protected $fillable = ['codigo', 'estado'];

    public function jugadores(): HasMany
    {
        return $this->hasMany(Jugador::class);
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(Partida::class);
    }

    /** Crea una sala nueva con un código de 4 letras único, reintentando ante colisión. */
    public static function crear(): self
    {
        for ($intento = 0; $intento < self::INTENTOS_CODIGO; $intento++) {
            try {
                return self::create([
                    'codigo' => self::generarCodigo(),
                    'estado' => self::ESPERANDO,
                ]);
            } catch (QueryException) {
                // código duplicado (constraint unique de la tabla): reintentar con uno nuevo
            }
        }

        throw new \RuntimeException('No se pudo generar un código de sala único.');
    }

    private static function generarCodigo(): string
    {
        $letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $codigo = '';
        for ($i = 0; $i < 4; $i++) {
            $codigo .= $letras[random_int(0, 25)];
        }

        return $codigo;
    }
}
