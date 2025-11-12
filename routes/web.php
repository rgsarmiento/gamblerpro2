<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\{
    MaquinasController,
    LecturasController,
    GastosController,
    CierresController,
    ProveedoresController,
    UserController, DashboardController
};

Route::redirect('/', '/login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/lecturas', [LecturasController::class, 'index'])->name('lecturas.index');
    Route::post('/lecturas', [LecturasController::class, 'store'])->name('lecturas.store');
    Route::put('/lecturas/{lecturas}', [LecturasController::class, 'update'])->name('lecturas.update');
    Route::delete('/lecturas/{lectura}', [LecturasController::class, 'destroy'])->name('lecturas.destroy');

    Route::post('/lecturas/confirmar', [LecturasController::class, 'confirmarLecturas'])->name('lecturas.confirmar');


    Route::get('/gastos', [GastosController::class, 'index'])->name('gastos.index');
    Route::post('/gastos', [GastosController::class, 'store'])->name('gastos.store');
    Route::delete('/gastos/{gasto}', [GastosController::class, 'destroy'])->name('gastos.destroy');

    Route::get('/cierres', [CierresController::class, 'index'])->name('cierres.index');
    
    Route::get('/proveedores', [ProveedoresController::class, 'index'])->name('proveedores.index');
    Route::post('/proveedores', [ProveedoresController::class, 'store'])->name('proveedores.store');
    Route::put('/proveedores/{proveedor}', [ProveedoresController::class, 'update'])->name('proveedores.update');
    Route::patch('/proveedores/{proveedor}/toggle', [ProveedoresController::class, 'toggleStatus'])->name('proveedores.toggle');
    Route::delete('/proveedores/{proveedor}', [ProveedoresController::class, 'destroy'])->name('proveedores.destroy');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});


Route::middleware(['auth', 'verified', 'role:master_admin|casino_admin'])->group(function () {   
    Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
    Route::patch('/usuarios/{user}/toggle', [UserController::class, 'toggleStatus'])->name('usuarios.toggle');
    Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');
    Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');
});

Route::middleware(['auth', 'verified', 'role:master_admin|casino_admin|sucursal_admin'])->group(function () {
    Route::get('/maquinas', [MaquinasController::class, 'index'])->name('maquinas.index');
    Route::post('/maquinas', [MaquinasController::class, 'store'])->name('maquinas.store');
    Route::put('/maquinas/{maquina}', [MaquinasController::class, 'update'])->name('maquinas.update');
    Route::delete('/maquinas/{maquina}', [MaquinasController::class, 'destroy'])->name('maquinas.destroy');
    Route::patch('/maquinas/{maquina}/toggle', [MaquinasController::class, 'toggle'])->name('maquinas.toggle');

});



// Route::get('/', function () {
//     return Inertia::render('Welcome');
// })->name('home');

// Route::get('dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
