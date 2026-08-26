<?php

namespace App\Http\Controllers;

class PaginaController extends Controller
{
    public function inicio()
    {
        return view('inicio');
    }

    /** Una sola vista sirve tanto la sala de espera como la mesa: el JS decide según lo que traiga el polling. */
    public function sala(string $codigo)
    {
        return view('sala', ['codigo' => strtoupper($codigo)]);
    }
}
