<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SupplierController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\PublicationController;

Route::group(['middleware' => ['jwt.verify']], function() {

    



});


    // ============================================
    // PROVEEDORES
    // ============================================
    Route::prefix('suppliers')->group(function () {
        // Listar proveedores
        Route::get('/', [SupplierController::class, 'index']);
        
        // Crear nuevo proveedor
        Route::post('/', [SupplierController::class, 'store']);
        
        // Actualizar proveedor
        Route::put('/{id}', [SupplierController::class, 'update']);
        
        // Eliminar proveedor
        Route::delete('/{id}', [SupplierController::class, 'destroy']);
    });

    // ============================================
    // COTIZACIONES
    // ============================================
    Route::prefix('quotes')->group(function () {
        // Listar cotizaciones
        Route::get('/', [QuoteController::class, 'index']);
        
        // Crear nueva cotización
        Route::post('/', [QuoteController::class, 'store']);

        // Actualizar cotización
        Route::put('/{id}', [QuoteController::class, 'update']);

        // Actualizar estado y/o tipo de entrega
        Route::put('/{id}/set_estado_and_entrega', [QuoteController::class, 'updateEstadoYEntrega']);

        // Ver detalle de cotización
        Route::get('/{id}', [QuoteController::class, 'show']);

        // Eliminar cotización
        Route::delete('/{id}', [QuoteController::class, 'destroy']);

        // Generar ticket de cotización
        Route::get('/{id}/ticket', [QuoteController::class, 'ticket']); 

        // Generar link de ticket de cotización acortado 
        Route::get('/{id}/ticket_acortado', [QuoteController::class, 'ticketAcortado']); 

        Route::put('/{id}/adelanto', [QuoteController::class, 'updateAdelanto']);

        // Route::get('/migrar/pagos/legacy', [QuoteController::class, 'migrarPagosLegacy']);
        Route::post('/{id}/pago', [QuoteController::class, 'registrarPago']);

        // Generar email de cotización
        // Route::get('/{id}/email', [QuoteController::class, 'emailQuote']);

        
    });

    // ============================================
    // PUBLICACIONES
    // ============================================
    Route::prefix('publications')->group(function () {
        // Listar publicaciones
        Route::get('/', [PublicationController::class, 'index']);
        
        // Crear nueva publicacion
        Route::post('/', [PublicationController::class, 'store']);

        // Obtener publicacion
        Route::get('/{id}', [PublicationController::class, 'show']);
        
        // Actualizar publicacion
        Route::put('/{id}/publish', [PublicationController::class, 'publish']);
        
        // Eliminar publicacion
        Route::delete('/{id}', [PublicationController::class, 'destroy']);
    });