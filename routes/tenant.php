<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TiendaController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EtiquetaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PedidoController;
use App\Http\Controllers\Admin\ProductoController;
use App\Http\Controllers\Admin\ProveedorController;
use App\Http\Controllers\Admin\PerfilController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// Todas las rutas de la tienda viven aquí.
// InitializeTenancyByDomain ya se aplica en RouteServiceProvider.
// PreventAccessFromCentralDomains impide que dominios centrales accedan a rutas de tenant.
Route::middleware([PreventAccessFromCentralDomains::class])->group(function () {

    // Rutas públicas de la tienda
    Route::get('/', [TiendaController::class, 'index'])->name('tienda.index');
    Route::get('/catalogo/{slug}', [TiendaController::class, 'catalogo'])->name('tienda.catalogo');
    // El id va en su propio segmento (no pegado al slug con guión) para que no haya
    // ambigüedad cuando el slug termina en número -- ej. /producto/jcb-991-00131/17080.
    // Ver Producto::url() y TiendaController::show().
    Route::get('/producto/{slug}/{producto}', [TiendaController::class, 'show'])
        ->name('tienda.show')
        ->where('producto', '[0-9]+')
        ->middleware('throttle:180,3');

    // Forma corta /producto/{id}: redirige a la canónica. Sólo matchea dígitos, así
    // que NO es ambigua -- /producto/jcb-991-00131 (un slug suelto) cae en 404, que
    // es justamente lo que buscamos.
    Route::get('/producto/{producto}', [TiendaController::class, 'showPorId'])
        ->name('tienda.show.id')
        ->where('producto', '[0-9]+')
        ->middleware('throttle:180,3');

    // TODO(public_id): retrocompatibilidad temporal con las URLs viejas
    // /producto/{uuid}, de cuando public_id era la route key. Se elimina junto con
    // la columna -- `grep -rn "TODO(public_id)"` lista todo lo que hay que tocar.
    Route::get('/producto/{public_id}', [TiendaController::class, 'showPorPublicId'])
        ->name('tienda.show.legacy')
        ->where('public_id', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}')
        ->middleware('throttle:180,3');
    Route::get('/filtros/valores', [TiendaController::class, 'filtrosValores'])->name('tienda.filtros.valores');
    Route::get('/productos/ajax', [TiendaController::class, 'productosAjax'])->name('tienda.productos.ajax');
    Route::get('/sitemap.xml', [SeoController::class, 'sitemapIndex'])->name('tienda.sitemap');
    Route::get('/sitemap-paginas.xml', [SeoController::class, 'sitemapPaginas'])->name('tienda.sitemap.paginas');
    Route::get('/sitemap-productos-{pagina}.xml', [SeoController::class, 'sitemapProductos'])
        ->where('pagina', '[0-9]+')
        ->name('tienda.sitemap.productos');
    Route::get('/robots.txt', [SeoController::class, 'robots'])->name('tienda.robots');

    // Rutas del carrito
    Route::prefix('carrito')->name('carrito.')->group(function () {
        Route::get('/', [CarritoController::class, 'index'])->name('index');
        Route::post('/agregar/{producto}', [CarritoController::class, 'agregar'])->name('agregar');
        Route::put('/actualizar/{producto}', [CarritoController::class, 'actualizar'])->name('actualizar');
        Route::delete('/eliminar/{producto}', [CarritoController::class, 'eliminar'])->name('eliminar');
        Route::delete('/vaciar', [CarritoController::class, 'vaciar'])->name('vaciar');
        Route::get('/checkout', [CarritoController::class, 'checkout'])->name('checkout');
        Route::post('/enviar', [CarritoController::class, 'enviarPedido'])->name('enviar');
        Route::get('/stock/{producto}', [CarritoController::class, 'stockProducto'])->name('stock');
    });

    // Rutas de autenticación
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Rutas de administración (protegidas)
    Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/productos/buscar', [ProductoController::class, 'buscarParaPedido'])->name('productos.buscar');
        Route::get('/productos/preview-slug', [ProductoController::class, 'previewSlug'])->name('productos.preview-slug');
        Route::get('/especificaciones/claves', [ProductoController::class, 'buscarEspecificacionClaves'])->name('especificaciones.claves');
        Route::get('/especificaciones/valores', [ProductoController::class, 'buscarEspecificacionValores'])->name('especificaciones.valores');
        Route::get('/productos/{producto}/historial', [ProductoController::class, 'historial'])->name('productos.historial');
        Route::post('/productos/{producto}/ajustar-stock', [ProductoController::class, 'ajustarStock'])->name('productos.ajustar-stock');

        Route::resource('productos', ProductoController::class);
        Route::resource('proveedores', ProveedorController::class)->parameters(['proveedores' => 'proveedor']);
        Route::resource('usuarios', UserController::class);
        Route::resource('perfiles', PerfilController::class)->parameters(['perfiles' => 'perfil']);
        Route::resource('etiquetas', EtiquetaController::class);
        Route::get('/etiquetas/{etiqueta}/valores', [EtiquetaController::class, 'buscarValores'])->name('etiquetas.valores');

        Route::get('/configuraciones', [ConfiguracionController::class, 'index'])->name('configuraciones.index');
        Route::put('/configuraciones', [ConfiguracionController::class, 'update'])->name('configuraciones.update');

        Route::get('/pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
        Route::get('/pedidos/create', [PedidoController::class, 'create'])->name('pedidos.create');
        Route::get('/pedidos/buscar-cliente', [PedidoController::class, 'buscarCliente'])->name('pedidos.buscar-cliente');
        Route::get('/pedidos/sugerir-clientes', [PedidoController::class, 'sugerirClientes'])->name('pedidos.sugerir-clientes');
        Route::post('/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
        Route::get('/pedidos/{pedido}', [PedidoController::class, 'show'])->name('pedidos.show');
        Route::post('/pedidos/{pedido}/confirmar', [PedidoController::class, 'confirmar'])->name('pedidos.confirmar');
        Route::post('/pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar'])->name('pedidos.cancelar');
        Route::put('/pedidos/{pedido}/productos/{item}', [PedidoController::class, 'updateProducto'])->name('pedidos.productos.update');
        Route::delete('/pedidos/{pedido}/productos/{item}', [PedidoController::class, 'destroyProducto'])->name('pedidos.productos.destroy');
        Route::post('/pedidos/{pedido}/productos', [PedidoController::class, 'addProducto'])->name('pedidos.productos.add');

        Route::resource('menus', MenuController::class);
        Route::post('/menus/reordenar', [MenuController::class, 'reordenar'])->name('menus.reordenar');
        Route::get('/menus/etiqueta/{etiqueta}/valores', [MenuController::class, 'valoresEtiqueta'])->name('menus.etiqueta.valores');
    });
});
