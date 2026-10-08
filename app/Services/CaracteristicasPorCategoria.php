<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Support\Caracteristica;
use Illuminate\Support\Facades\DB;

/**
 * Qué características pide cada categoría, con la herencia resuelta.
 *
 * Reglas (plan, sección 4):
 *  - Las de una categoría = las globales + las de sus madres + las propias, en
 *    ese orden. Si una se repite, manda la más cercana: "Remeras" puede volver
 *    obligatoria una que en "Ropa" es opcional, o al revés.
 *  - Las de un producto = las de todas sus categorías; obligatoria si alguna la exige.
 *
 * Carga todo de una vez (categorías y reglas son pocas filas): crear una instancia
 * por pedido y preguntarle lo que haga falta.
 */
class CaracteristicasPorCategoria
{
    /** @var \Illuminate\Support\Collection categorías por id, con parent cargado */
    private $categorias;

    /** @var array categoria_id => [filas de categoria_caracteristica en orden] */
    private $reglas = [];

    /** @var array etiqueta_id => Etiqueta */
    private $etiquetas = [];

    /** @var Caracteristica[] */
    private $globales = [];

    public function __construct()
    {
        $this->categorias = Categoria::enOrdenDeArbol()->keyBy('id');

        foreach (Etiqueta::orderBy('nombre')->get() as $etiqueta) {
            $this->etiquetas[$etiqueta->id] = $etiqueta;
            if ($etiqueta->global) {
                $this->globales[] = $this->armar($etiqueta, false, null, null);
            }
        }

        foreach (DB::table('categoria_caracteristica')->orderBy('orden')->orderBy('id')->get() as $fila) {
            $this->reglas[$fila->categoria_id][] = $fila;
        }
    }

    /** @return Caracteristica[] las globales: las pide todo producto */
    public function globales(): array
    {
        return $this->globales;
    }

    /** @return Caracteristica[] las que pide una categoría, en orden y sin repetir */
    public function deCategoria(int $categoriaId): array
    {
        $categoria = $this->categorias->get($categoriaId);
        if (!$categoria) {
            return $this->globales;
        }

        $resultado = [];
        foreach ($this->globales as $global) {
            $resultado[$global->etiquetaId()] = $global;
        }

        // De la raíz hacia abajo: la más cercana pisa a la de arriba en su lugar.
        foreach (array_reverse($categoria->linaje()) as $nivel) {
            foreach ($this->reglas[$nivel->id] ?? [] as $fila) {
                $etiqueta = $this->etiquetas[$fila->etiqueta_id] ?? null;
                if ($etiqueta) {
                    $resultado[$etiqueta->id] = $this->armar($etiqueta, (bool) $fila->obligatoria, $nivel->id, $nivel->nombre);
                }
            }
        }

        return array_values($resultado);
    }

    /**
     * @param int[] $categoriaIds las categorías de un producto
     * @return Caracteristica[]
     */
    public function deCategorias(array $categoriaIds): array
    {
        $resultado = [];
        foreach ($this->globales as $global) {
            $resultado[$global->etiquetaId()] = $global;
        }

        foreach (array_unique(array_map('intval', $categoriaIds)) as $id) {
            foreach ($this->deCategoria($id) as $caracteristica) {
                $ya = $resultado[$caracteristica->etiquetaId()] ?? null;
                $resultado[$caracteristica->etiquetaId()] = $ya
                    ? $ya->obligatoriaSi($caracteristica->obligatoria())
                    : $caracteristica;
            }
        }

        return array_values($resultado);
    }

    /** @return Caracteristica[] las heredadas de una categoría (globales y de sus madres), sin las propias */
    public function heredadas(int $categoriaId): array
    {
        $categoria = $this->categorias->get($categoriaId);

        return $categoria && $categoria->parent_id
            ? $this->deCategoria($categoria->parent_id)
            : $this->globales;
    }

    /** Lo que necesita el formulario de producto: por categoría, sus características. */
    public function mapaParaFormulario(): array
    {
        $mapa = ['globales' => array_map(function (Caracteristica $c) { return $c->aArray(); }, $this->globales), 'porCategoria' => []];
        foreach ($this->categorias as $id => $categoria) {
            $mapa['porCategoria'][$id] = array_map(function (Caracteristica $c) { return $c->aArray(); }, $this->deCategoria($id));
        }
        return $mapa;
    }

    private function armar(Etiqueta $etiqueta, bool $obligatoria, ?int $categoriaId, ?string $origen): Caracteristica
    {
        return new Caracteristica($etiqueta->id, $etiqueta->nombre, (bool) $etiqueta->visible_usuarios, $obligatoria, $categoriaId, $origen);
    }
}
