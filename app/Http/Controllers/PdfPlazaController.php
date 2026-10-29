<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

use App\Models\User;
use App\Models\Quote;
use App\Models\QuoteDetail;
use App\Models\Supplier;

class PdfPlazaController extends Controller
{
    public function getSuppliersConPedidosEnCurso($userId)
    {
        // 1. Obtener Proveedores que tenga al menos un detalle
        //    asociado a una cotización activa ("en curso" y no eliminada).
        $suppliers = Supplier::select(
            'id',
            'razon_social',
            'direccion',
            'telefono',
            'contacto'
        )
        ->whereHas('quoteDetails', function ($query) use ($userId) {
            $query->pedidosEnCurso($userId);
        })
        ->get();

        if (count($suppliers)==0) {
            return response()->json([
                'success' => false,
                'message' => 'No hay proveedores con pedidos en curso en este momento.'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $suppliers
        ], 200);
    }

    public function pdfPedidosEnCursoSupplier($supplierId, $userId)
    {
        // 1. Obtener Proveedor que tenga al menos un detalle
        //    asociado a una cotización activa ("en curso" y no eliminada).
        $suppliers = Supplier::select(
            'id',
            'razon_social',
            'direccion',
            'telefono',
            'contacto'
        )
        ->where('id', $supplierId)
        ->whereHas('quoteDetails', function ($query) use ($supplierId, $userId) {
            $query->pedidosEnCursoSupplier($supplierId, $userId);
        })
        ->with(['quoteDetails' => function ($query) use ($supplierId, $userId) {
            // Eager loading filtrando solo los detalles pendientes
            $query->pedidosEnCursoSupplier($supplierId, $userId)
                  ->with([
                        'quote:id,folio,cliente,telefono,email,estado',

                        // 'publication.images'
                        // 1. Carga solo 'id' de la publicación
                        'publication:id', 
                        
                        // 2. Carga solo 'id' y 'publication_id' de la tabla de imágenes 
                        //    (publication_id es necesario para que Laravel pueda asociarlas)
                        'publication.images:id,publication_id,image_path'

                    ]); // Cargar la relación quote y las imágenes de la publicación asociada
        }])
        ->get();

        if (count($suppliers)==0) {
            return response()->json([
                'success' => false,
                'message' => 'El proveedor no tiene pedidos en curso en este momento.'
            ], 404);
        }

        $total_vendido   = 0;
        $total_entregado = 0;

        foreach ($suppliers as $supplier) {
            $sumaTotal = $supplier->quoteDetails->sum('total');

            $total_vendido   = round($sumaTotal, 2);
            $total_entregado = round($sumaTotal * 0.90, 2);
        }

        // 2. Transformar y agrupar la estructura para Angular 
        $rutaRecoleccion = $suppliers->map(function ($supplier) {
            
            // Agrupar los detalles del proveedor por cada Quote
            $quotesAgrupadas = $supplier->quoteDetails
                ->groupBy('quote_id')
                ->map(function ($detalles) {
                    $quote = $detalles->first()->quote;

                    return [
                        'id' => $quote->id,
                        'folio' => $quote->folio,
                        'cliente' => $quote->cliente,
                        'telefono' => $quote->telefono,
                        'email' => $quote->email,
                        'estado' => $quote->estado,
                        'detalles' => $detalles->map(function ($detalle) {
                            $pendiente = (float) $detalle->cantidad - (float) $detalle->cantidad_recolectada;

                            // Obtener imágenes si el detalle tiene una publicación asociada
                            $imagenes = [];
                            if ($detalle->publication && $detalle->publication->images) {
                                // Extraer solo la propiedad 'url' que ya genera automáticamente el accessor getUrlAttribute()
                                $imagenes = $detalle->publication->images->pluck('url')->toArray();
                            }

                            return [
                                'id' => $detalle->id,
                                'quote_id' => $detalle->quote_id,
                                'supplier_id' => $detalle->supplier_id,
                                'publication_id' => $detalle->publication_id,
                                'imagenes' => $imagenes,
                                'modelo' => $detalle->modelo,
                                'talla' => $detalle->talla,
                                'color' => $detalle->color,
                                'cantidad_total' => (float) $detalle->cantidad,
                                'cantidad_recolectada' => (float) $detalle->cantidad_recolectada,
                                'cantidad_pendiente' => $pendiente,
                                'precio_unitario' => (float) $detalle->precio_unitario,
                            ];
                        })->values()
                    ];
                })->values();

            return [
                'id' => $supplier->id,
                'razon_social' => $supplier->razon_social,
                'direccion' => $supplier->direccion,
                'telefono' => $supplier->telefono,
                'contacto' => $supplier->contacto,
                'quotes' => $quotesAgrupadas
            ];
        });

        $data = [
            'r' => '52',
            'g' => '152',
            'b' => '219',
            'header' => public_path('images/ordenPlazaVestido/BARRA-SUPERIOR-ORDENES.jpg'),
            'footer' => public_path('images/ordenPlazaVestido/BARRA-INFERIOR.jpeg'),
            'logo' => public_path('images/ordenPlazaVestido/logoPlazaDelVestido.png'),

            'total_vendido' => $total_vendido,
            'total_entregado' => $total_entregado,
            'rutaRecoleccion' => $rutaRecoleccion
        ];

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('pdfs_plaza.pedidosCursoSupplier', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs/pedidosCursoSupplier/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/pedidosCursoSupplier/' . $nombreArchivo);

        return response()->json([
            'success' => true,
            'data' => $url
        ], 200);
    }


}
