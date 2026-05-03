<?php

use App\Http\Controllers\Central\TenantController;
use Illuminate\Support\Facades\Route;

// Solo accesible desde dominios centrales
Route::middleware('central')->group(function () {
    Route::get('/', function () { return redirect()->route('tenants.index'); })->name('central.home');

    Route::resource('tenants', TenantController::class)
        ->only(['index', 'create', 'store', 'destroy']);
});
