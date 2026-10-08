<?php

namespace App\Support;

use App\Models\User;

/**
 * Los temas de la Ayuda del panel (menú lateral › Ayuda).
 *
 * Cada tema es una de las ayudas de resources/views/admin/ayudas: el mismo texto
 * que abre "Cómo funciona" en su pantalla, así no hay dos versiones que se
 * desactualicen. Cada persona ve los temas de lo que puede usar (el permiso de la
 * pantalla a la que lleva). Las palabras sirven al buscador: lo que alguien
 * escribiría ("talle", "precio", "whatsapp") aunque no esté en el título.
 */
final class TemasDeAyuda
{
    /** slug => [título, ícono, vista, ruta de la pantalla, botón, permiso, resumen, palabras] */
    private const TEMAS = [
        'primeros-pasos' => ['Primeros pasos', 'bi-flag', 'admin.ayudas.primeros-pasos', null, null, null,
            'Armá tu tienda en 5 pasos.', 'empezar comenzar arrancar nueva tienda inicio'],
        'categorias' => ['Categorías', 'bi-diagram-3', 'admin.ayudas.categorias', 'admin.categorias.index', 'Ir a Categorías', 'categorias.ver',
            'Los estantes de tu negocio: dónde va cada producto.', 'estante gondola rubro seccion orden destacados subcategoria'],
        'caracteristicas' => ['Características', 'bi-ui-checks', 'admin.ayudas.caracteristicas', 'admin.etiquetas.index', 'Ir a Características', 'etiquetas.ver',
            'Los datos de cada producto: marca, sabor, contenido.', 'dato marca sabor contenido filtro obligatoria etiqueta'],
        'productos' => ['Productos', 'bi-box-seam', 'admin.ayudas.productos', 'admin.productos.index', 'Ir a Productos', 'productos.ver',
            'Cargar, editar y encontrar tus productos.', 'cargar alta precio stock foto imagen codigo descripcion'],
        'variantes' => ['Talles, colores y variantes', 'bi-grid-3x3-gap', 'admin.ayudas.variantes', 'admin.productos.index', 'Ir a Productos', 'productos.ver',
            'Un producto que viene en varios talles o colores.', 'talle color sabor variante opcion propiedad stock'],
        'importar' => ['Excel', 'bi-file-earmark-spreadsheet', 'admin.ayudas.importar', 'admin.importaciones.index', 'Ir a Excel', 'productos.importar',
            'Cargar o cambiar muchos productos de una vez.', 'excel planilla importar exportar masivo precios muchos'],
        'pedidos' => ['Pedidos', 'bi-bag-check', 'admin.ayudas.pedidos', 'admin.pedidos.index', 'Ir a Pedidos', 'pedidos.ver',
            'Qué pasa cuando un cliente te compra.', 'venta compra cliente whatsapp confirmar cancelar'],
        'menus' => ['Menú de la tienda', 'bi-menu-button-wide', 'admin.ayudas.menus', 'admin.menus.index', 'Ir a Menú de la tienda', 'menus.ver',
            'Los botones de arriba de tu tienda.', 'menu navegacion botones barra'],
        'ajustes' => ['Ajustes', 'bi-sliders', 'admin.ayudas.ajustes', 'admin.configuraciones.index', 'Ir a Ajustes', 'configuraciones.ver',
            'Nombre, logo, colores, WhatsApp y más.', 'logo nombre color whatsapp redes google precios configuracion'],
        'proveedores' => ['Proveedores', 'bi-truck', 'admin.ayudas.proveedores', 'admin.proveedores.index', 'Ir a Proveedores', 'proveedores.ver',
            'A quién le comprás cada producto.', 'proveedor distribuidor compra'],
        'monedas' => ['Monedas', 'bi-currency-exchange', 'admin.ayudas.monedas', 'admin.monedas.index', 'Ir a Monedas', 'monedas.ver',
            'Precios en dólares y cotización.', 'dolar cotizacion moneda pesos'],
        'usuarios' => ['Usuarios y perfiles', 'bi-people', 'admin.ayudas.usuarios', 'admin.usuarios.index', 'Ir a Usuarios', 'usuarios.ver',
            'Tu equipo y qué puede hacer cada uno.', 'equipo empleado permiso perfil acceso contraseña'],
        'preguntas-frecuentes' => ['Preguntas frecuentes', 'bi-chat-square-text', 'admin.ayudas.preguntas-frecuentes', null, null, null,
            'Las dudas de todos los días.', 'pregunta duda no aparece problema'],
    ];

    /**
     * Los temas que puede ver este usuario, en orden.
     *
     * @return array<string, array> slug => tema
     */
    public static function para(User $usuario): array
    {
        $temas = [];
        foreach (array_keys(self::TEMAS) as $slug) {
            $tema = self::tema($slug);
            if ($tema['permiso'] === null || $usuario->puede($tema['permiso'])) {
                $temas[$slug] = $tema;
            }
        }

        return $temas;
    }

    /** El tema, o null si no existe o el usuario no puede verlo. */
    public static function delUsuario(string $slug, User $usuario): ?array
    {
        return self::para($usuario)[$slug] ?? null;
    }

    private static function tema(string $slug): array
    {
        [$titulo, $icono, $vista, $ruta, $boton, $permiso, $resumen, $palabras] = self::TEMAS[$slug];

        return compact('slug', 'titulo', 'icono', 'vista', 'ruta', 'boton', 'permiso', 'resumen', 'palabras');
    }
}
