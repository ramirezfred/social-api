<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SupplierController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\CashCloseController;
use App\Http\Controllers\PublicationLinkController;

use App\Http\Controllers\PruebasController;

Route::group(['middleware' => ['jwt.verify']], function() {

    
    // ============================================
    // GASTOS
    // ============================================
    Route::prefix('expenses')->group(function () {
        // Listar gastos
        Route::get('/', [ExpenseController::class, 'index']);
        
        // Crear nuevo gasto
        Route::post('/', [ExpenseController::class, 'store']);
        
        // Actualizar gasto
        Route::put('/{id}', [ExpenseController::class, 'update']);
        
        // Eliminar gasto
        Route::delete('/{id}', [ExpenseController::class, 'destroy']);
    });

    // ============================================
    // NOMINAS    
    // ============================================
    Route::prefix('payrolls')->group(function () {
        // Listar nominas
        Route::get('/', [PayrollController::class, 'index']);
        
        // Crear nueva nomina
        Route::post('/', [PayrollController::class, 'store']);
        
        // Actualizar nomina
        Route::put('/{id}', [PayrollController::class, 'update']);
        
        // Eliminar nomina
        Route::delete('/{id}', [PayrollController::class, 'destroy']);
    });
   

});


    // ============================================
    // PROVEEDORES
    // ============================================
    Route::prefix('suppliers')->group(function () {
        // 1. RUTAS ESTÁTICAS (Sin parámetros)
        Route::get('/', [SupplierController::class, 'index']);
        Route::post('/', [SupplierController::class, 'store']);

        // 2. RUTAS CON PARÁMETROS (MÁS ESPECÍFICAS PRIMERO)
        Route::get('/contactos/{tipo}', [SupplierController::class, 'getContactosByTipo']);
        Route::get('/{id}/generado-por-semana', [SupplierController::class, 'getGeneradoPorSemana']);
        
        // 3. RUTAS CATCH-ALL CON {id} (SIEMPRE AL FINAL)
        Route::put('/{id}', [SupplierController::class, 'update']);
        Route::delete('/{id}', [SupplierController::class, 'destroy']);

        
    });

    // ============================================
    // COTIZACIONES
    // ============================================
    // Route::prefix('quotes')->group(function () {
    //     // Listar cotizaciones
    //     Route::get('/', [QuoteController::class, 'index']);
        
    //     // Crear nueva cotización
    //     Route::post('/', [QuoteController::class, 'store']);

    //     // Actualizar cotización
    //     Route::put('/{id}', [QuoteController::class, 'update']);

    //     // Actualizar estado y/o tipo de entrega
    //     Route::put('/{id}/set_estado_and_entrega', [QuoteController::class, 'updateEstadoYEntrega']);

    //     // Ver detalle de cotización
    //     Route::get('/{id}', [QuoteController::class, 'show']);

    //     // Eliminar cotización
    //     Route::delete('/{id}', [QuoteController::class, 'destroy']);

    //     // Generar ticket de cotización
    //     Route::get('/{id}/ticket', [QuoteController::class, 'ticket']); 

    //     // Generar link de ticket de cotización acortado 
    //     Route::get('/{id}/ticket_acortado', [QuoteController::class, 'ticketAcortado']); 

    //     Route::put('/{id}/adelanto', [QuoteController::class, 'updateAdelanto']);

    //     // Route::get('/migrar/pagos/legacy', [QuoteController::class, 'migrarPagosLegacy']);
    //     Route::post('/{id}/pago', [QuoteController::class, 'registrarPago']);

    //     // Generar email de cotización
    //     // Route::get('/{id}/email', [QuoteController::class, 'emailQuote']);

    //     // Route::get('/eliminar/pago/{id}', [QuoteController::class, 'destroyPago']);
    //     // Route::get('/set/estado/{id}', [QuoteController::class, 'setEstado']);

    //     Route::get('/reporte/semanal', [QuoteController::class, 'reporteSemanal']);
    //     Route::get('/reporte/general', [QuoteController::class, 'reporteGeneral']);
    //     Route::get('/reporte/generalSimple', [QuoteController::class, 'reporteGeneralSimple']);
    //     Route::get('/reporte/generalIngresosEgresos', [PruebasController::class, 'reporteIngresosEgresos']);

    //     Route::get('/get/estadisticas', [QuoteController::class, 'getEstadisticas']);
        
    // });

    Route::prefix('quotes')->group(function () {
        // 1. RUTAS ESTÁTICAS (Sin parámetros)
        Route::get('/', [QuoteController::class, 'index']);
        Route::post('/', [QuoteController::class, 'store']);
        
        Route::get('/reporte/semanal', [QuoteController::class, 'reporteSemanal']);
        Route::get('/reporte/general', [QuoteController::class, 'reporteGeneral']);
        Route::get('/reporte/generalSimple', [QuoteController::class, 'reporteGeneralSimple']);
        Route::get('/reporte/ingresosEgresos', [QuoteController::class, 'reporteIngresosEgresos']);
        Route::get('/get/estadisticas', [QuoteController::class, 'getEstadisticas']);
        
        // Route::get('/migrar/pagos/legacy', [QuoteController::class, 'migrarPagosLegacy']);

        // 2. RUTAS CON PARÁMETROS (MÁS ESPECÍFICAS PRIMERO)
        Route::put('/{id}/set_estado_and_entrega', [QuoteController::class, 'updateEstadoYEntrega']);
        Route::get('/{id}/ticket', [QuoteController::class, 'ticket']);
        Route::get('/{id}/ticket_acortado', [QuoteController::class, 'ticketAcortado']);
        Route::put('/{id}/adelanto', [QuoteController::class, 'updateAdelanto']);
        Route::post('/{id}/pago', [QuoteController::class, 'registrarPago']);
        
        // Route::get('/{id}/email', [QuoteController::class, 'emailQuote']);
        // Route::get('/eliminar/pago/{id}', [QuoteController::class, 'destroyPago']);
        // Route::get('/set/estado/{id}', [QuoteController::class, 'setEstado']);
        
        // 3. RUTAS CATCH-ALL CON {id} (SIEMPRE AL FINAL)
        Route::put('/{id}', [QuoteController::class, 'update']);
        Route::get('/{id}', [QuoteController::class, 'show']);
        Route::delete('/{id}', [QuoteController::class, 'destroy']);
    });

    // ============================================
    // PUBLICACIONES
    // ============================================
    Route::prefix('publications')->group(function () {
        // 1. RUTAS ESTÁTICAS (Sin parámetros)
        Route::get('/', [PublicationController::class, 'index']);
        Route::post('/', [PublicationController::class, 'store']);
        
        Route::get('/crear/catalogo', [PublicationController::class, 'catalogo']);
        Route::get('/pruebas/catalogo', [PruebasController::class, 'catalogo']);
        Route::get('/borrar/publicaciones/pruebas', [PublicationController::class, 'destroyTestPublications']);

        // 2. RUTAS CON PARÁMETROS (MÁS ESPECÍFICAS PRIMERO)
        Route::get('/{id}/publicacion/normal', [PublicationController::class, 'showNormal']); // ← Más específica
        Route::put('/{id}/publish', [PublicationController::class, 'publish']);
        Route::post('/{id}/editar', [PublicationController::class, 'editar']);
        
        // 3. RUTAS CATCH-ALL CON {id} (SIEMPRE AL FINAL)
        Route::get('/{id}', [PublicationController::class, 'show']);  // ← Esta siempre última
        Route::delete('/{id}', [PublicationController::class, 'destroy']);
        
    });

    // ============================================
    // EMPLEADOS
    // ============================================
    Route::prefix('employees')->group(function () {
        // Listar empleados
        Route::get('/', [EmployeeController::class, 'index']);
        
        // Crear nuevo empleado
        Route::post('/', [EmployeeController::class, 'store']);
        
        // Actualizar empleado
        Route::put('/{id}', [EmployeeController::class, 'update']);

        // Actualizar contraseña de empleado
        Route::put('/{id}/password', [EmployeeController::class, 'updatePassword']);
        
        // Eliminar empleado
        Route::delete('/{id}', [EmployeeController::class, 'destroy']);
    });

    // ============================================
    // CORTE DE CAJA
    // ============================================
    Route::prefix('cash-closes')->group(function () {

        // Lista de cortes de caja
        Route::get('/', [CashCloseController::class, 'index']);

        // Listar cierres de caja pendientes por proveedor
        Route::get('/proveedores-pendientes', [CashCloseController::class, 'getPendingSuppliers']);
    
        // Listar cortes globales (B y C)
        Route::get('/globales', [CashCloseController::class, 'getGlobalCuts']);

        // Guardar corte A (pago a proveedor)
        Route::post('/corte-proveedor', [CashCloseController::class, 'closeSupplier']);

        // Guardar corte B (ingresos menos gastos)
        Route::post('/corte-ingresos-gastos', [CashCloseController::class, 'storeCorteIngresosGastos']);

        // Guardar corte C (comisión + envíos)
        Route::post('/corte-comision-envios', [CashCloseController::class, 'storeCorteComisionEnvios']);

        // ticket de corte de caja
        Route::get('/{id}/ticket', [CashCloseController::class, 'ticket']);
    });

    // ============================================
    // LINKS PARA PUBLICACIONES
    // ============================================
    Route::middleware('jwt.verify')->prefix('publication-links')->group(function () {

        Route::get('/', [PublicationLinkController::class, 'index']);
        Route::post('/', [PublicationLinkController::class, 'store']);
        Route::put('/{id}', [PublicationLinkController::class, 'update']);
        Route::delete('/{id}', [PublicationLinkController::class, 'destroy']);
        Route::get('/{id}/url', [PublicationLinkController::class, 'getUrl']);
        
    });

    // Pública
    Route::get(
        '/publication-links/validate/{token}',
        [PublicationLinkController::class, 'validateToken']
    );