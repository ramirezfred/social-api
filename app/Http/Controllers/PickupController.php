<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

use App\Models\Quote;
use App\Models\QuoteDetail;
use App\Models\Supplier;

class PickupController extends Controller
{
    /**
     * Genera la ruta de recolección agrupada por Proveedor y Cotización,
     * filtrando items recolectados u ordenando por dirección.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRutaRecoleccion()
    {
        // 1. Obtener Proveedores que tengan al menos un detalle pendiente
        //    asociado a una cotización activa ("en curso" y no eliminada).
        $suppliers = Supplier::select(
            'id',
            'razon_social',
            'direccion',
            'telefono',
            'contacto'
        )
        ->whereHas('quoteDetails', function ($query) {
            $query->pendientesRecoleccion();
        })
        ->with(['quoteDetails' => function ($query) {
            // Eager loading filtrando solo los detalles pendientes
            $query->pendientesRecoleccion()
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

        // 2. Ordenar proveedores por dirección (Pasillo X Local Y)
        $suppliersOrdenados = $suppliers
        ->sortBy(function ($supplier) {
            return $this->getDireccionOrden($supplier->direccion);
        })
        ->values();

        // 3. Transformar y agrupar la estructura para Angular 
        $rutaRecoleccion = $suppliersOrdenados->map(function ($supplier) {
            
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

        return response()->json([
            'success' => true,
            'data' => $rutaRecoleccion
        ], 200);
    }

    private function getDireccionOrden($direccion)
    {
        $direccion = strtolower(trim($direccion ?? ''));

        // Eliminar cualquier prefijo antes de "Pasillo"
        if (($pos = stripos($direccion, 'pasillo')) !== false) {
            $direccion = substr($direccion, $pos);
        }

        if (preg_match('/pasillos?\s*(\d+).*?local(?:es)?\s*(\d+)/i', $direccion, $m)) {
            return sprintf('0-%05d-%05d', (int) $m[1], (int) $m[2]);
        }

        return '1-' . $direccion;
    }

    /**
     * Endpoint para que Angular actualice la cantidad recolectada de un detalle.
     *
     * @param Request $request
     * @param int $detalleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function marcarRecolectado(Request $request, $detalleId)
    {
        $validator = Validator::make($request->all(),[
            'cantidad' => 'nullable|numeric|min:0.0001'
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data'=>$validator->errors(),
            ],422);
        }

        $detalle = QuoteDetail::find($detalleId);

        if (!$detalle)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$detalleId
            ], 404);
        }

        // Si no envía cantidad, se asume que recolectó TODO lo que faltaba
        $pendiente = $detalle->cantidad - $detalle->cantidad_recolectada;
        $cantidadAnadir = $request->input('cantidad', $pendiente);

        // Incrementar lo recolectado
        $detalle->cantidad_recolectada += $cantidadAnadir;

        // Evitar que la cantidad recolectada supere el total de la orden
        if ($detalle->cantidad_recolectada > $detalle->cantidad) {
            $detalle->cantidad_recolectada = $detalle->cantidad;
        }

        $detalle->save();

        return response()->json([
            'success' => true,
            'message' => 'Progreso de recolección actualizado correctamente.',
            'data' => [
                'id' => $detalle->id,
                'cantidad_total' => (float) $detalle->cantidad,
                'cantidad_recolectada' => (float) $detalle->cantidad_recolectada,
                'cantidad_pendiente' => (float) ($detalle->cantidad - $detalle->cantidad_recolectada)
            ]
        ], 200);
    }

    public function getRutaRecoleccionPdf()
    {
        // Llamar directamente al método
        $response = $this->getRutaRecoleccion();

        // Obtener el contenido de la respuesta como array
        $contenido = $response->getData(true); // true = array asociativo
        
        // Acceder a la data
        $ruta = $contenido['data']; // Aquí tienes los datos que necesitas
        
        // return $data;

        $data = [
            'r' => '52',
            'g' => '152',
            'b' => '219',
            'header' => public_path('images/ordenPlazaVestido/BARRA-SUPERIOR-REPORTE.jpeg'),
            'footer' => public_path('images/ordenPlazaVestido/BARRA-INFERIOR.jpeg'),

            'rutaRecoleccion' => $ruta
        ];

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('recolecciones.rutaGeneral', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs/recolecciones/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/recolecciones/' . $nombreArchivo);

        return response()->json([
            'success' => true,
            'data' => $url
        ], 200);
    }
}
