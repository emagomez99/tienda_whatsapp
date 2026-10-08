<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\TemasDeAyuda;

/**
 * Menú lateral › Ayuda: todos los temas en un lugar, con un buscador. Cada tema es
 * la misma ayuda que abre "Cómo funciona" en su pantalla (ver TemasDeAyuda).
 */
class AyudaController extends Controller
{
    public function index()
    {
        $temas = TemasDeAyuda::para(auth()->user());

        // Para el buscador: el texto de cada ayuda, sin etiquetas, en minúsculas.
        foreach ($temas as $slug => $tema) {
            $texto = strip_tags(view($tema['vista'])->render());
            $temas[$slug]['buscar'] = mb_strtolower(preg_replace('/\s+/', ' ', $tema['titulo'] . ' ' . $tema['resumen'] . ' ' . $tema['palabras'] . ' ' . $texto), 'UTF-8');
        }

        return view('admin.ayuda.index', compact('temas'));
    }

    public function show(string $tema)
    {
        $tema = TemasDeAyuda::delUsuario($tema, auth()->user());
        abort_if($tema === null, 404);

        return view('admin.ayuda.show', compact('tema'));
    }
}
