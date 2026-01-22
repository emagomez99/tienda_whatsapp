<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\TiendaController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EtiquetaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\ProductoController;
use App\Http\Controllers\Admin\ProveedorController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// Rutas públicas de la tienda
Route::get('/', [TiendaController::class, 'index'])->name('tienda.index');
Route::get('/producto/{producto}', [TiendaController::class, 'show'])->name('tienda.show');

// Rutas del carrito
Route::prefix('carrito')->name('carrito.')->group(function () {
    Route::get('/', [CarritoController::class, 'index'])->name('index');
    Route::post('/agregar/{producto}', [CarritoController::class, 'agregar'])->name('agregar');
    Route::put('/actualizar/{producto}', [CarritoController::class, 'actualizar'])->name('actualizar');
    Route::delete('/eliminar/{producto}', [CarritoController::class, 'eliminar'])->name('eliminar');
    Route::delete('/vaciar', [CarritoController::class, 'vaciar'])->name('vaciar');
    Route::get('/checkout', [CarritoController::class, 'checkout'])->name('checkout');
    Route::post('/enviar', [CarritoController::class, 'enviarPedido'])->name('enviar');
});

// Rutas de autenticación
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Rutas de administración (protegidas)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Productos
    Route::resource('productos', ProductoController::class);

    // Proveedores
    Route::resource('proveedores', ProveedorController::class);

    // Usuarios
    Route::resource('usuarios', UserController::class);

    // Etiquetas
    Route::resource('etiquetas', EtiquetaController::class);
    Route::get('/etiquetas/{etiqueta}/valores', [EtiquetaController::class, 'buscarValores'])->name('etiquetas.valores');

    // Autocomplete especificaciones
    Route::get('/especificaciones/claves', [ProductoController::class, 'buscarEspecificacionClaves'])->name('especificaciones.claves');
    Route::get('/especificaciones/valores', [ProductoController::class, 'buscarEspecificacionValores'])->name('especificaciones.valores');

    // Configuraciones
    Route::get('/configuraciones', [ConfiguracionController::class, 'index'])->name('configuraciones.index');
    Route::put('/configuraciones', [ConfiguracionController::class, 'update'])->name('configuraciones.update');

    // Menús
    Route::resource('menus', MenuController::class);
    Route::post('/menus/reordenar', [MenuController::class, 'reordenar'])->name('menus.reordenar');
    Route::get('/menus/categoria/{categoria}/valores', [MenuController::class, 'valoresCategoria'])->name('menus.categoria.valores');
});
