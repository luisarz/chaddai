<?php

use App\Http\Controllers\DTEController;
use App\Http\Controllers\hoja;
use App\Http\Controllers\OrdenController;
use App\Http\Controllers\SenEmailDTEController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('/ejecutar', [hoja::class, 'ejecutar']);
Route::get('/generarDTE/{idVenta}', [DTEController::class, 'generarDTE'])->middleware(['auth'])->name('generarDTE');
Route::get('/sendAnularDTE/{idVenta}', [DTEController::class, 'anularDTE'])->middleware(['auth'])->name('sendAnularDTE');
Route::get('/printDTE/{idVenta}', [DTEController::class, 'printDTE'])->middleware(['auth'])->name('printDTE');
Route::get('/sendDTE/{idVenta}', [SenEmailDTEController::class, 'SenEmailDTEController'])->middleware(['auth'])->name('sendDTE');
Route::get('/ordenPrint/{idVenta}', [OrdenController::class, 'generarPdf'])->middleware(['auth'])->name('ordenGenerarPdf');
Route::get('/closeCashboxPrint/{idCasboxClose}', [OrdenController::class, 'closeClashBoxPrint'])->middleware(['auth'])->name('closeClashBoxPrint');
Route::get('/admin/sales/{idVenta}/edit', [OrdenController::class, 'billingOrder'])->middleware(['auth'])->name('billingOrder');

require __DIR__.'/auth.php';
