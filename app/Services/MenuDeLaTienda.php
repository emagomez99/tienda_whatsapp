<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Menu;
use App\Support\OpcionDeMenu;
use Illuminate\Support\Collection;

/**
 * El menú que ve el cliente: el armado a mano en Admin › Menús, o uno automático
 * con las categorías visibles (como Tiendanube).
 *
 * Una tienda nueva arranca con el automático. Una que ya tenía menús armados los
 * conserva hasta que elija el automático (decisiones D8 y D9 del plan).
 */
class MenuDeLaTienda
{
    const ORIGEN_CATEGORIAS = 'categorias';
    const ORIGEN_PROPIO     = 'propio';

    const CLAVE_AJUSTE = 'menu_origen';

    public static function origen(): string
    {
        $elegido = Configuracion::obtener(self::CLAVE_AJUSTE);
        if (in_array($elegido, [self::ORIGEN_CATEGORIAS, self::ORIGEN_PROPIO], true)) {
            return $elegido;
        }

        return Menu::exists() ? self::ORIGEN_PROPIO : self::ORIGEN_CATEGORIAS;
    }

    public static function elegirOrigen(string $origen): void
    {
        Configuracion::establecer(self::CLAVE_AJUSTE, $origen, 'De dónde sale el menú de la tienda: categorias o propio');
    }

    public static function esAutomatico(): bool
    {
        return self::origen() === self::ORIGEN_CATEGORIAS;
    }

    /**
     * Las opciones de primer nivel, con sus hijas.
     *
     * Se arma una vez por pedido: la página dibuja el menú dos veces (escritorio y
     * celular).
     *
     * @return Collection|OpcionDeMenu[]
     */
    public static function opciones(): Collection
    {
        $request = request();
        if (!$request->attributes->has('menu_de_la_tienda')) {
            $request->attributes->set('menu_de_la_tienda', self::esAutomatico() ? self::desdeCategorias() : self::desdeMenus());
        }

        return $request->attributes->get('menu_de_la_tienda');
    }

    private static function desdeMenus(): Collection
    {
        $convertir = function ($menus) use (&$convertir) {
            return $menus->map(function (Menu $menu) use ($convertir) {
                return new OpcionDeMenu(
                    'm' . $menu->id,
                    $menu->nombre,
                    $menu->esContenedor() ? null : $menu->url,
                    $convertir($menu->childrenActivos)
                );
            })->values();
        };

        return $convertir(Menu::getArbolMenu());
    }

    /** Las categorías visibles en su orden; una oculta se lleva a sus subcategorías. */
    private static function desdeCategorias(): Collection
    {
        $visibles = Categoria::enOrdenDeArbol()->filter(function (Categoria $c) {
            return $c->esVisibleEnTienda();
        });
        $porMadre = $visibles->groupBy(function (Categoria $c) { return $c->parent_id ?: 0; });

        $armar = function ($madreId) use (&$armar, $porMadre) {
            return collect($porMadre->get($madreId, []))->map(function (Categoria $c) use ($armar) {
                return new OpcionDeMenu('c' . $c->id, $c->nombre, $c->url(), $armar($c->id));
            })->values();
        };

        return $armar(0);
    }
}
