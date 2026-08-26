<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Jugador extends Model
{
    protected $fillable = ['sala_id', 'nombre', 'token', 'puesto'];

    /** El token es la prueba de identidad del jugador al reconectar: nunca se le manda al rival. */
    protected $hidden = ['token'];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }
}
