<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Partida extends Model
{
    protected $fillable = ['sala_id', 'estado', 'version'];

    protected $casts = [
        'estado' => 'array',
        'version' => 'integer',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }
}
