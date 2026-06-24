<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'home')->name('/');

Route::get('/plano-imprimir', function () {
    $datos = session('datos_lote', []);
    // dd($datos);
    return view('planos2d', compact('datos'));
})->name('plano.imprimir');


//ruta ventanas
Route::livewire('/ventanas', 'ventanas.home')->name('ventanas');
//ruta puertas
Route::livewire('/puertas', 'puertas.home')->name('puertas');

//curvo
Route::livewire('/curvo', 'curvo.home')->name('curvo');
