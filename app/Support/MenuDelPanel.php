<?php

namespace App\Support;

use App\Models\User;

/**
 * Las opciones del menú lateral del panel, agrupadas como las piensa el dueño de la
 * tienda: vender, armar el catálogo, ajustar la tienda y su equipo.
 *
 * Es una lista de datos y no una cadena de @if en el layout: así se ve de un vistazo
 * qué hay, en qué grupo y con qué permiso, y el layout sólo la dibuja (ver
 * docs/plan-diseno-panel.md). Cada opción: [texto, ícono, ruta, rutas que la marcan
 * activa, permiso].
 */
class MenuDelPanel
{
    private const GRUPOS = [
        null => [
            ['Inicio', 'bi-house', 'admin.dashboard', 'admin.dashboard', 'dashboard.ver'],
        ],
        'Ventas' => [
            ['Pedidos', 'bi-bag-check', 'admin.pedidos.index', 'admin.pedidos.*', 'pedidos.ver'],
        ],
        'Catálogo' => [
            ['Productos', 'bi-box-seam', 'admin.productos.index', 'admin.productos.*', 'productos.ver'],
            // Categorías y Características van juntas: cada estante dice qué datos pide.
            ['Categorías', 'bi-diagram-3', 'admin.categorias.index', 'admin.categorias.*', 'categorias.ver'],
            ['Características', 'bi-ui-checks', 'admin.etiquetas.index', 'admin.etiquetas.*', 'etiquetas.ver'],
            ['Proveedores', 'bi-truck', 'admin.proveedores.index', 'admin.proveedores.*', 'proveedores.ver'],
        ],
        'Tienda' => [
            ['Menú de la tienda', 'bi-menu-button-wide', 'admin.menus.index', 'admin.menus.*', 'menus.ver'],
            ['Ajustes', 'bi-sliders', 'admin.configuraciones.index', 'admin.configuraciones.*', 'configuraciones.ver'],
            ['Monedas', 'bi-currency-exchange', 'admin.monedas.index', 'admin.monedas.*', 'monedas.ver'],
        ],
        'Equipo' => [
            ['Usuarios', 'bi-people', 'admin.usuarios.index', 'admin.usuarios.*', 'usuarios.ver'],
            ['Perfiles', 'bi-shield-check', 'admin.perfiles.index', 'admin.perfiles.*', 'perfiles.ver'],
        ],
    ];

    /**
     * Los grupos con las opciones que el usuario puede ver; los grupos vacíos no
     * aparecen.
     *
     * @return array<string, array<int, array{texto: string, icono: string, url: string, activa: bool}>>
     *         clave '' = sin título (Inicio)
     */
    public static function para(User $usuario): array
    {
        $grupos = [];
        foreach (self::GRUPOS as $titulo => $opciones) {
            foreach ($opciones as $opcion) {
                [$texto, $icono, $ruta, $patron, $permiso] = $opcion;
                if (!$usuario->puede($permiso)) {
                    continue;
                }
                $grupos[(string) $titulo][] = [
                    'texto'  => $texto,
                    'icono'  => $icono,
                    'url'    => route($ruta),
                    'activa' => request()->routeIs($patron),
                ];
            }
        }

        return $grupos;
    }
}
