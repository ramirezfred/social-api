<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

use App\Models\Supplier;
use App\Models\Quote;
use App\Models\QuoteDetail;
use App\Models\QuotePago;
use App\Models\User;
use App\Models\CashClose;

use Exception;

use Carbon\Carbon;

class CashCloseController extends Controller
{
    public function index(Request $request)
    {
        $query = CashClose::query()
            ->with([ 
                'supplier:id,razon_social'
            ]);

        // Filtro por fechas
        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $coleccion = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $coleccion,
        ]);
    }

    public function getPendingSuppliers()
    {
        // Agrupamos los detalles pendientes por proveedor con montos redondeados
        $pending = DB::table('quote_details')
            ->join('suppliers', 'quote_details.supplier_id', '=', 'suppliers.id')
            ->join('quotes', 'quote_details.quote_id', '=', 'quotes.id')
            ->where('quotes.estado', 'finalizada') // Solo ingresos reales
            ->where('quote_details.pago_proveedor_estado', 'pendiente')
            ->select(
                'suppliers.id as supplier_id',
                'suppliers.razon_social',
                DB::raw('ROUND(SUM(quote_details.total), 2) as total_vendido'),
                DB::raw('ROUND(SUM(quote_details.total) * 0.90, 2) as total_deuda')
            )
            ->groupBy('suppliers.id', 'suppliers.razon_social')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pending,
        ]);
    }

    public function getGlobalCuts()
    {
        // Buscar últimas fechas de corte
        $lastIngresosCorte = DB::table('cash_closes')->where('tipo', 'ingresos_gastos')->latest()->first();
        $lastComisionCorte = DB::table('cash_closes')->where('tipo', 'comision_envios')->latest()->first();

        $fechaInicioIngresos = $lastIngresosCorte ? $lastIngresosCorte->created_at : '2026-01-01 00:00:00';
        $fechaInicioComision = $lastComisionCorte ? $lastComisionCorte->created_at : '2026-01-01 00:00:00';

        // ==========================================
        // ---- CALCULO B: Ingresos Menos Gastos ----
        // ==========================================
        // Desglosamos comisión y envíos del periodo B para poder retornarlos por separado
        $dataCorteB = DB::table('quotes')
            ->where('estado', 'finalizada')
            //->where('created_at', '>', $fechaInicioIngresos)
            ->where('corte_b_estado', 'pendiente') 
            ->select(
                DB::raw('SUM(subtotal * 0.10) as comision_b'),
                DB::raw('SUM(envio) as envios_b')
            )
            ->first();

        $totalComisionB = $dataCorteB->comision_b ?? 0;
        $totalEnviosB = $dataCorteB->envios_b ?? 0;
        $totalIngresos = $totalComisionB + $totalEnviosB; // Equivale a (subtotal * 0.10 + envio)

        $totalGastosB = DB::table('expenses')
            ->where('eliminado', 0)
            // ->where('created_at', '>', $fechaInicioIngresos)
            ->where('corte_b_estado', 'pendiente')
            ->sum('monto') ?? 0;

        $corte_ingresos_gastos = $totalIngresos - $totalGastosB;


        // ==========================================
        // ---- CALCULO C: Comisión + Envíos ----
        // ==========================================
        $dataCorteC = DB::table('quotes')
            ->where('estado', 'finalizada')
            // ->where('created_at', '>', $fechaInicioComision)
            ->where('corte_c_estado', 'pendiente')
            ->select(
                DB::raw('SUM(subtotal * 0.10) as total_comision'),
                DB::raw('SUM(envio) as total_envios')
            )
            ->first();

        $totalComisionC = $dataCorteC->total_comision ?? 0;
        $totalEnviosC = $dataCorteC->total_envios ?? 0;
        
        $corte_comision_envios = $totalComisionC + $totalEnviosC;


        // ==========================================
        // ---- RETORNO DE LA DATA PARA EL FRONT ----
        // ==========================================
        return response()->json([
            // Totales Finales
            'corte_ingresos_gastos'  => round($corte_ingresos_gastos, 2),
            'corte_comision_envios'  => round($corte_comision_envios, 2),
            
            // Desglose Corte B (Calculados con base en $fechaInicioIngresos)
            'total_comision_b'       => round($totalComisionB, 2), // Te sugiero usar alias distintos para no sobreescribir en el JSON si el front es estricto
            'total_envios_b'         => round($totalEnviosB, 2),
            'total_gastos_b'           => round($totalGastosB, 2),
            
            // Desglose Corte C (Calculados con base en $fechaInicioComision)
            'total_comision_c'         => round($totalComisionC, 2),
            'total_envios_c'           => round($totalEnviosC, 2),
            
            // Metadatos de control
            'periodo_ingresos_desde' => $fechaInicioIngresos,
            'periodo_comision_desde' => $fechaInicioComision
        ]);
    }

    // Corte B: Guardar Ingresos Menos Gastos
    public function storeCorteIngresosGastos()
    {
        $lastCorte = CashClose::where('tipo', 'ingresos_gastos')->latest()->first();
        $fechaInicio = $lastCorte ? $lastCorte->created_at : '2026-01-01 00:00:00';

        // 10% del subtotal + envíos de quotes finalizadas en el periodo
        $dataQuotes = DB::table('quotes')
            ->where('estado', 'finalizada')
            // ->where('created_at', '>', $fechaInicio)
            ->where('corte_b_estado', 'pendiente')
            ->select(
                DB::raw('SUM(subtotal * 0.10) as total_comision'),
                DB::raw('SUM(envio) as total_envios')
            )->first();

        $comision = $dataQuotes->total_comision ?? 0;
        $envios = $dataQuotes->total_envios ?? 0;

        $totalGastos = DB::table('expenses')
            ->where('eliminado', 0)
            // ->where('created_at', '>', $fechaInicio)
            ->where('corte_b_estado', 'pendiente')
            ->sum('monto') ?? 0;

        $montoFinal = $comision + $envios - $totalGastos;

        $close = new CashClose();
        $close->tipo = 'ingresos_gastos';
        $close->monto = round($montoFinal, 2);
        
        // Campos extras solicitados para Corte B:
        $close->total_comision = round($comision, 2);
        $close->total_envios = round($envios, 2);
        $close->total_gastos = round($totalGastos, 2);
        $close->save();

        // Cambiar estatus a cerrado 
        DB::table('quotes')
            ->where('estado', 'finalizada')
            // ->where('created_at', '>', $fechaInicio)
            ->where('corte_b_estado', 'pendiente')
            ->update([
                'corte_b_estado' => 'cerrado',
                'cash_close_b_id' => $close->id
            ]);

        DB::table('expenses')
            ->where('eliminado', 0)
            // ->where('created_at', '>', $fechaInicio)
            ->where('corte_b_estado', 'pendiente')
            ->update([
                'corte_b_estado' => 'cerrado',
                'cash_close_b_id' => $close->id
            ]);

        return response()->json(['message' => 'Corte B guardado con éxito', 'data' => $close]);
    }

    // Corte C: Guardar Comisión + Envíos
    public function storeCorteComisionEnvios()
    {
        $lastCorte = CashClose::where('tipo', 'comision_envios')->latest()->first();
        $fechaInicio = $lastCorte ? $lastCorte->created_at : '2026-01-01 00:00:00';

        $dataQuotes = DB::table('quotes')
            ->where('estado', 'finalizada')
            // ->where('created_at', '>', $fechaInicio)
            ->where('corte_c_estado', 'pendiente')
            ->select(
                DB::raw('SUM(subtotal * 0.10) as total_comision'),
                DB::raw('SUM(envio) as total_envios')
            )->first();

        $comision = $dataQuotes->total_comision ?? 0;
        $envios = $dataQuotes->total_envios ?? 0;
        $montoFinal = $comision + $envios;

        if ($montoFinal <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Para ejecutar el corte el acumulado debe ser mayor a cero.'
            ], 400);
        }

        $close = new CashClose();
        $close->tipo = 'comision_envios';
        $close->monto = round($montoFinal, 2);
        
        // Campos extras solicitados para Corte C:
        $close->total_comision = round($comision, 2);
        $close->total_envios = round($envios, 2);
        $close->save();

        // Cambiar estatus a cerrado 
        DB::table('quotes')
            ->where('estado', 'finalizada')
            // ->where('created_at', '>', $fechaInicio)
            ->where('corte_c_estado', 'pendiente')
            ->update([
                'corte_c_estado' => 'cerrado',
                'cash_close_c_id' => $close->id
            ]);

        return response()->json(['message' => 'Corte C guardado con éxito', 'data' => $close]);
    }

    // Corte A: Guardar pago a proveedor
    public function closeSupplier(Request $request)
    {   
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            // 'admin_password' => 'required',
            'payment_method' => 'required|in:efectivo,transferencia'
        ]);

        // 1. Validar contraseña de Admin (asumiendo que el Auth::user() es admin o buscando uno)
        // $admin = Auth::user(); // O buscar específicamente un usuario admin si este es un empleado
        // if (!Hash::check($request->admin_password, $admin->password)) {
        //     return response()->json(['error' => 'Contraseña de administrador incorrecta'], 403);
        // }

        DB::beginTransaction();
        try {
            $totals = DB::table('quote_details')
                ->join('quotes', 'quote_details.quote_id', '=', 'quotes.id')
                ->where('quotes.estado', 'finalizada')
                ->where('quote_details.supplier_id', $request->supplier_id)
                ->where('quote_details.pago_proveedor_estado', 'pendiente')
                ->select(
                    DB::raw('ROUND(SUM(quote_details.total), 2) as total_vendido'),
                    DB::raw('ROUND(SUM(quote_details.total) * 0.90, 2) as total_entregado')
                )->first();

            if (!$totals->total_vendido) {
                return response()->json(['error' => 'No hay saldo pendiente para este proveedor'], 400);
            }

            // Crear el registro guardando la métrica extra del Corte A
            $close = new CashClose();
            $close->tipo = 'proveedor';
            $close->monto = $totals->total_entregado; // El monto neto a pagar
            $close->supplier_id = $request->supplier_id;
            $close->metodo_pago = $request->payment_method;
            
            // Campo extra solicitado:
            $close->total_vendido = $totals->total_vendido; 
            $close->save();

            // Cambiar estatus de deudas a pagada
            DB::table('quote_details')
                ->where('supplier_id', $request->supplier_id)
                ->where('pago_proveedor_estado', 'pendiente')
                ->whereIn('quote_id', function($query) {
                    $query->select('id')
                        ->from('quotes')
                        ->where('estado', 'finalizada');
                })
                ->update([
                    'pago_proveedor_estado' => 'pagado',
                    'cash_close_id' => $close->id
                ]);

            if($close->tipo == 'proveedor'){
                // Generar PDF
                $urlPdf = $this->comprobantePdf($close->id);
                $close->pdf = $urlPdf;
                $close->save();
            }

            DB::commit();
            return response()->json(['message' => 'Corte A guardado con éxito', 'data' => $close]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error en el servidor'], 500);
        }
    }

    /**
     * Reimprimir ticket de una orden
     */
    public function ticket($id)
    {
        $close = CashClose::find($id);

        if (!$close) {
            return response()->json([
                'success' => false,
                'message' => 'Corte no encontrado.'
            ], 404);
        }

        if($close->tipo !== 'proveedor') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden generar comprobantes PDF para cortes de tipo proveedor'
            ], 400);
        }

        // if(!$close->pdf){
        //     // Generar PDF
        //     $urlPdf = $this->comprobantePdf($close->id);
        //     $close->pdf = $urlPdf;
        //     $close->save();
        // }

        //Provisionalmente
        $urlPdf = $this->comprobantePdf($close->id);
        $close->pdf = $urlPdf;
        $close->save();

        return response()->json([
            'success' => true,
            'data' => $close->pdf
        ]);
    }

    public function comprobantePdf($id)
    {
        set_time_limit(500);

        $close = CashClose::with('supplier')->find($id);

        if(!$close) {
            return response()->json([
                'success' => false,
                'message' => 'Corte no encontrado'
            ], 404);
        }

        if($close->tipo !== 'proveedor') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden generar comprobantes PDF para cortes de tipo proveedor'
            ], 400);
        }

        // $rgb = UtilitiesService::hexToRgb('#4285cb');

        $data = [
            'r' => '',
            'g' => '',
            'b' => '',
            'header' => public_path('images/ordenPlazaVestido/BARRA-SUPERIOR-REPORTE.jpeg'),
            'footer' => public_path('images/ordenPlazaVestido/BARRA-INFERIOR.jpeg'),

            'data' => $close,
        ];

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('comprobantes.corte-proveedor', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs/comprobantes/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/comprobantes/' . $nombreArchivo);

        return $url;
    }

}
