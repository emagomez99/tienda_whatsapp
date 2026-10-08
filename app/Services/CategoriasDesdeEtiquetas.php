<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\EtiquetaValor;
use App\Models\Menu;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Pasa a categorías lo que hoy dicen las etiquetas "Categoria" y "Subcategoria",
 * y los menús que filtraban por ellas a menús "Los de una categoría".
 * Ver la sección 6 del plan (docs/plan-categorias-y-caracteristicas.md).
 *
 * Usa las categorías que ya existen: si alguien armó "Producto › Notebooks" a
 * mano, el valor "Notebooks" va ahí y no a una "Notebooks" nueva al lado. Sólo
 * crea la que no encuentra, o si el nombre se repite y no sabe cuál es.
 *
 * Sólo agrega: no quita categorías a ningún producto ni borra las etiquetas (eso
 * va en la limpieza, cuando el usuario lo haya revisado). Correrlo dos veces da lo
 * mismo que una.
 */
class CategoriasDesdeEtiquetas
{
    const ETIQUETA_CATEGORIA    = 'categoria';
    const ETIQUETA_SUBCATEGORIA = 'subcategoria';

    /** @var AsignacionDeCategorias */
    private $asignacion;

    /** @var array lo que pasó, para mostrarlo */
    private $informe;

    public function __construct(AsignacionDeCategorias $asignacion = null)
    {
        $this->asignacion = $asignacion ?: new AsignacionDeCategorias();
    }

    /**
     * @param array $porProveedor nombre de proveedor => camino de categoría ("Hercules" => "Kits hidráulicos")
     * @return array informe: creadas, usadas, asignados, menus, menusSinConvertir, sinCategoria
     */
    public function ejecutar(array $porProveedor = []): array
    {
        $this->informe = [
            'creadas' => [], 'usadas' => [], 'asignados' => [],
            'menus' => [], 'menusSinConvertir' => [], 'sinCategoria' => 0,
        ];

        $antes = Categoria::pluck('id')->all();

        $categoria    = $this->etiqueta(self::ETIQUETA_CATEGORIA);
        $subcategoria = $this->etiqueta(self::ETIQUETA_SUBCATEGORIA);

        if ($categoria || $subcategoria) {
            $this->desdeEtiquetas($categoria, $subcategoria);
        }

        foreach ($porProveedor as $nombreProveedor => $camino) {
            $this->desdeProveedor($nombreProveedor, $camino);
        }

        // Al final, con todos los productos ya en sus categorías, para comparar bien.
        if ($categoria || $subcategoria) {
            $this->convertirMenus($categoria, $subcategoria);
        }

        // Las creadas, contando las madres que se crearon de paso ("Accesorios" al crear "Accesorios › Hidráulica").
        foreach (Categoria::enOrdenDeArbol() as $c) {
            if (!in_array($c->id, $antes, true)) {
                $this->informe['creadas'][$c->id] = $c->camino();
            }
        }
        $this->informe['usadas'] = array_diff_key($this->informe['usadas'], $this->informe['creadas']);
        $this->informe['sinCategoria'] = Producto::sinCategoria()->count();

        return $this->informe;
    }

    private function etiqueta(string $normalizado): ?Etiqueta
    {
        // Sin tildes: "Categoría" y "Categoria" son la misma.
        return Etiqueta::all()->first(function (Etiqueta $e) use ($normalizado) {
            return Str::ascii(EtiquetaValor::normalizar($e->nombre)) === $normalizado;
        });
    }

    /** Cada producto, a la categoría que arman sus dos etiquetas ("Accesorios > Hidráulica"). */
    private function desdeEtiquetas(?Etiqueta $categoria, ?Etiqueta $subcategoria): void
    {
        $valores = function (?Etiqueta $etiqueta) {
            return $etiqueta
                ? DB::table('producto_etiqueta')->where('etiqueta_id', $etiqueta->id)->pluck('valor', 'producto_id')->all()
                : [];
        };
        $deCategoria = $valores($categoria);
        $deSubcategoria = $valores($subcategoria);

        $porCamino = [];
        foreach (array_unique(array_merge(array_keys($deCategoria), array_keys($deSubcategoria))) as $productoId) {
            $partes = array_values(array_filter([
                trim((string) ($deCategoria[$productoId] ?? '')),
                trim((string) ($deSubcategoria[$productoId] ?? '')),
            ], 'strlen'));
            if ($partes) {
                $porCamino[implode(' ' . Categoria::SEPARADOR . ' ', $partes)][] = $productoId;
            }
        }

        foreach ($porCamino as $camino => $productos) {
            $destino = $this->ubicar(explode(' ' . Categoria::SEPARADOR . ' ', $camino));
            $this->asignar($destino, Producto::whereIn('productos.id', $productos));
        }
    }

    private function desdeProveedor(string $nombreProveedor, string $camino): void
    {
        $proveedor = Proveedor::where('nombre', 'ilike', $nombreProveedor)->first();
        if (!$proveedor) {
            throw new InvalidArgumentException("No hay un proveedor «{$nombreProveedor}».");
        }

        $destino = $this->ubicar(array_map('trim', explode(Categoria::SEPARADOR, $camino)));
        $this->asignar($destino, Producto::where('proveedor_id', $proveedor->id));
    }

    /**
     * La categoría para un camino: la que ya existe con ese nombre (y esa madre,
     * si el camino la dice), estando donde esté; si no hay o hay más de una, la
     * del camino tal cual, creándola si hace falta.
     */
    private function ubicar(array $partes): Categoria
    {
        $hoja = EtiquetaValor::normalizar(end($partes));
        $madre = count($partes) > 1 ? EtiquetaValor::normalizar($partes[count($partes) - 2]) : null;

        $candidatas = Categoria::with('parent')->where('normalizado', $hoja)->get()
            ->filter(function (Categoria $c) use ($madre) {
                return $madre === null || ($c->parent && $c->parent->normalizado === $madre);
            });

        if ($candidatas->count() === 1) {
            $this->anotar('usadas', $candidatas->first());
            return $candidatas->first();
        }

        $camino = implode(' ' . Categoria::SEPARADOR . ' ', $partes);
        $categoria = Categoria::resolverCamino($camino, true);
        $this->anotar('usadas', $categoria);

        return $categoria;
    }

    private function asignar(Categoria $categoria, $productos): void
    {
        $nuevos = $this->asignacion->asignar($productos, [$categoria->id]);
        $camino = $categoria->camino();
        $this->informe['asignados'][$camino] = ($this->informe['asignados'][$camino] ?? 0) + $nuevos;
    }

    private function anotar(string $tipo, Categoria $categoria): void
    {
        $this->informe[$tipo][$categoria->id] = $categoria->camino();
    }

    /**
     * Con dos categorías del mismo nombre ("Accesorios › Hidráulica" e "Industrial ›
     * Hidráulica"), se queda con la que está debajo de algo que nombran los menús
     * de arriba: "Hidráulica" dentro del menú "Accesorios" es la de Accesorios.
     */
    private function desempatar($candidatas, Menu $menu)
    {
        if ($candidatas->count() < 2) {
            return $candidatas;
        }

        $pistas = [];
        foreach (array_slice($menu->linaje(), 1) as $arriba) {
            $pistas[] = EtiquetaValor::normalizar($arriba->nombre);
            if ($arriba->enlace_valor) {
                $pistas[] = EtiquetaValor::normalizar($arriba->enlace_valor);
            }
        }

        return $candidatas->filter(function (Categoria $c) use ($pistas) {
            foreach (array_slice($c->linaje(), 1) as $madre) {
                if (in_array($madre->normalizado, $pistas, true)) {
                    return true;
                }
            }
            return false;
        })->values();
    }

    /** Los menús "Categoria = X" pasan a "Los de la categoría X". */
    private function convertirMenus(?Etiqueta $categoria, ?Etiqueta $subcategoria): void
    {
        $ids = array_filter([$categoria ? $categoria->id : null, $subcategoria ? $subcategoria->id : null]);

        $menus = Menu::where('tipo_enlace', Menu::TIPO_ETIQUETA)->whereIn('enlace_id', $ids)->orderBy('id')->get();
        foreach ($menus as $menu) {
            $valor = trim((string) $menu->enlace_valor);
            if ($valor === '') {
                $this->informe['menusSinConvertir'][$menu->nombre] = 'muestra todo lo que tenga la etiqueta, sin un valor: revisalo a mano';
                continue;
            }

            $candidatas = $this->desempatar(Categoria::where('normalizado', EtiquetaValor::normalizar($valor))->get(), $menu);
            if ($candidatas->count() !== 1) {
                $this->informe['menusSinConvertir'][$menu->nombre] = $candidatas->isEmpty()
                    ? "ningún producto tiene «{$valor}», no hay categoría a la que apuntar"
                    : "hay varias categorías «{$valor}»: elegí la correcta a mano";
                continue;
            }

            // Lo que ve el cliente en ese menú, antes y después: si cambia, que se vea.
            $antes = Producto::visiblesEnTienda()->delMenu($menu)->count();
            $menu->update(['tipo_enlace' => Menu::TIPO_CATEGORIA, 'enlace_id' => $candidatas->first()->id, 'enlace_valor' => null]);
            $despues = Producto::visiblesEnTienda()->delMenu($menu->fresh())->count();

            $this->informe['menus'][$menu->nombre] = $candidatas->first()->camino()
                . ($antes === $despues ? ($despues === 1 ? ' (el mismo producto)' : " (los mismos {$despues} productos)") : " (CAMBIA: mostraba {$antes}, va a mostrar {$despues})");
        }
    }
}
