<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

use App\Models\Supplier;
use App\Models\Quote;
use App\Models\QuoteDetail;
use App\Models\QuotePago;
use App\Models\User;

use Exception;

use Carbon\Carbon;

// use App\Services\UtilitiesService;

class QuoteController extends Controller
{
    /**
     * Listar cotizaciones (no eliminadas)
     */
    public function index(Request $request)
    {
        $query = Quote::noEliminados();

        if ($request->filled('estado')) {

            if ($request->estado == 'finalizada') {
                $query->whereIn('estado', ['finalizada', 'cancelada']);
            } else {
                $query->where('estado', $request->estado);
            }

        }

        // Filtro por fechas
        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $coleccion = $query->with(['detalles.supplier', 'pagos'])
            ->withSum('detalles as nro_productos', 'cantidad')
            ->orderBy('id', 'desc')
            ->get();

        $stats = $this->stats($request->user_id);

        return response()->json([
            'success' => true,
            'estadisticas' => $stats,
            'data' => $coleccion,
            
        ], 200);
    }

    /**
     * Crear una cotización con sus detalles
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'user_id' => 'required|numeric',
            'folio' => 'nullable|string',
            'cliente' => 'required|string|max:255',
            'email' => 'nullable|email|max:150',
            'telefono' => 'required|string|max:30',

            'moneda' => 'required|string|max:4|in:MXN,USD', //MXN o USD
            'envio' => 'nullable|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'descuento' => 'required|numeric|min:0',
            'impuesto' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',

            'notas' => 'nullable|string',

            'detalles' => 'required|array|min:1',
            'detalles.*.supplier_id' => 'required|exists:suppliers,id',
            'detalles.*.modelo' => 'required|string|max:255',
            'detalles.*.talla' => 'required|string|max:255',
            'detalles.*.color' => 'required|string|max:255',
            'detalles.*.cantidad' => 'required|numeric|min:0.0001',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.porcentaje_desc' => 'required|numeric|min:0|max:100',
            'detalles.*.porcentaje_impuesto' => 'required|numeric|min:0|max:100',
            'detalles.*.subtotal' => 'required|numeric|min:0',
            'detalles.*.impuesto' => 'required|numeric|min:0',
            'detalles.*.descuento' => 'required|numeric|min:0',
            'detalles.*.total' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 422);
        }

        $user = User::buscarPorId($request->input('user_id'));
        if (!$user)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'Usuario no encontrado.'
            ], 404);
        }

        if($user->status != 1)
        {
            return response()->json([
                'success' => false,
                'message'=>'El usuario no está activo.'
            ], 422);
        }

        foreach ($request->detalles as $detalle) {
            $proveedor = Supplier::noEliminados()
                ->where('id', $detalle['supplier_id'])
                ->first();
            if (!$proveedor)
            {
                // Devolvemos error codigo http 404
                return response()->json([
                    'success' => false,
                    'message'=>'No existe el proveedor con ID '.$detalle['supplier_id']
                ], 404);
            }
        }

        DB::beginTransaction();

        try {

            $quote = Quote::create([
                'user_id' => $request->user_id,
                'cliente' => $request->cliente,
                'email' => $request->email ?? null,
                'telefono' => $request->telefono,
                'estado' => 'en curso', // en curso
                'moneda' => $request->moneda,
                'envio' => $request->envio,
                'subtotal' => $request->subtotal,
                'descuento' => $request->descuento,
                'impuesto' => $request->impuesto,
                'total' => $request->total,
                'pago_estado' => 'pendiente', 
                'notas' => $request->notas ?? null,
            ]);

            foreach ($request->detalles as $detalle) {

                // Guardar detalle de cotizacion
                $detalleCotizacion = QuoteDetail::create([
                    'quote_id' => $quote->id,
                    'supplier_id' => $detalle['supplier_id'],
                    'modelo' => $detalle['modelo'],
                    'talla' => $detalle['talla'],
                    'color' => $detalle['color'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'porcentaje_desc' => $detalle['porcentaje_desc'],
                    'porcentaje_impuesto' => $detalle['porcentaje_impuesto'],
                    'subtotal' => $detalle['subtotal'],
                    'impuesto' => $detalle['impuesto'],
                    'descuento' => $detalle['descuento'],
                    'total' => $detalle['total'],
                ]);
            }

            $folio = 'COT-' . str_pad($quote->id, 4, '0', STR_PAD_LEFT);

            // Actualizar folio de la cotizacion
            $quote->update([
                'folio' => $folio,
            ]);

            // $document = $this->comprobantePdf($quote->id);

            // $quote->update([
            //     'pdf' => $document,
            // ]);

            DB::commit();

            // $quote->load('opportunity', 'detalles.product');

            $quote = Quote::with('detalles.supplier', 'pagos')
                ->withSum('detalles as nro_productos', 'cantidad')
                ->find($quote->id);

            //Email a Antonio
            $this->emailQuote($quote->id);

            return response()->json([
                'success' => true,
                'message' => 'Cotización registrada con éxito.',
                'data' => $quote
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la Cotización. ' . $e->getMessage(),
            ], 500);

        }
    }

    /**
     * Actualizar una cotización (y sus detalles)
     */
    public function update(Request $request, $id)
    {

        $quote = Quote::find($id);

        if (!$quote) {
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'cliente' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|max:150',
            'telefono' => 'sometimes|string|max:30',

            'moneda' => 'sometimes|string|max:4|in:MXN,USD', // Validar valores permitidos
            'envio' => 'sometimes|nullable|numeric|min:0',
            'subtotal' => 'sometimes|numeric|min:0',
            'descuento' => 'sometimes|numeric|min:0',
            'impuesto' => 'sometimes|numeric|min:0',
            'total' => 'sometimes|numeric|min:0',

            'notas' => 'sometimes|nullable|string',

            'detalles' => 'sometimes|array|min:1',
            'detalles.*.supplier_id' => 'required_with:detalles|exists:suppliers,id',
            'detalles.*.modelo' => 'required_with:detalles|string|max:255',
            'detalles.*.talla' => 'required_with:detalles|string|max:255',
            'detalles.*.color' => 'required_with:detalles|string|max:255',
            'detalles.*.cantidad' => 'required_with:detalles|numeric|min:0.0001',
            'detalles.*.precio_unitario' => 'required_with:detalles|numeric|min:0',
            'detalles.*.porcentaje_desc' => 'required_with:detalles|numeric|min:0|max:100',
            'detalles.*.porcentaje_impuesto' => 'required_with:detalles|numeric|min:0|max:100',
            'detalles.*.subtotal' => 'required_with:detalles|numeric|min:0',
            'detalles.*.impuesto' => 'required_with:detalles|numeric|min:0',
            'detalles.*.descuento' => 'required_with:detalles|numeric|min:0',
            'detalles.*.total' => 'required_with:detalles|numeric|min:0',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 422);
        }

        // capturar el estado anterior
        $oldEstado = $quote->estado;

        // impedir editar cotización finalizada/cancelada
        if ($quote->estado != 'en curso') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede editar una cotización Finalizada o Cancelada.'
            ], 422);
        }

        // if ($quote->pago_estado === 'pagado') {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'No se puede editar una cotización Pagada.'
        //     ], 422);
        // }

        DB::beginTransaction();

        try {

            // Actualizar cabecera
            // Toma solo los campos que vienen en la petición
            $quote->update($request->only([
                'cliente',
                'email',
                'telefono',
                'moneda',
                'envio',
                'subtotal',
                'descuento',
                'impuesto',
                'total',
                'notas',
            ]));

            // Si cambió el total
            if ($request->filled('total')) {
                // Sincronizar pago_estado en la tabla quotes
                $quote->update(['pago_estado' => $this->calcularPagoEstado($quote)]);
            }

            // Si vienen nuevos detalles, se reemplazan
            if ($request->filled('detalles')) {

                foreach ($request->detalles as $detalle) {
                    $proveedor = Supplier::noEliminados()
                        ->where('id', $detalle['supplier_id'])
                        ->first();
                    if (!$proveedor)
                    {
                        // Devolvemos error codigo http 404
                        return response()->json([
                            'success' => false,
                            'message'=>'No existe el proveedor con ID '.$detalle['supplier_id']
                        ], 404);
                    }
                }

                $quote->detalles()->delete();

                foreach ($request->detalles as $detalle) {

                    // Guardar detalle de cotizacion
                    $detalleCotizacion = QuoteDetail::create([
                        'quote_id' => $quote->id,
                        'supplier_id' => $detalle['supplier_id'],
                        'modelo' => $detalle['modelo'],
                        'talla' => $detalle['talla'],
                        'color' => $detalle['color'],
                        'cantidad' => $detalle['cantidad'],
                        'precio_unitario' => $detalle['precio_unitario'],
                        'porcentaje_desc' => $detalle['porcentaje_desc'],
                        'porcentaje_impuesto' => $detalle['porcentaje_impuesto'],
                        'subtotal' => $detalle['subtotal'],
                        'impuesto' => $detalle['impuesto'],
                        'descuento' => $detalle['descuento'],
                        'total' => $detalle['total'],
                    ]);
                }

            }

            // Generar nuevo PDF xq hubo cambios en la cotización
            $document = $this->comprobantePdf($quote->id);
            $quote->update([
                'pdf' => $document,
            ]);
            
            DB::commit();

            $quote = Quote::with('detalles.supplier', 'pagos')
                ->withSum('detalles as nro_productos', 'cantidad')
                ->find($quote->id);

            return response()->json([
                'success' => true,
                'message' => 'Cotización actualizada correctamente',
                'data' => $quote
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la cotización. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar el estado y lugar de entrega de una cotización
     */
    public function updateEstadoYEntrega(Request $request, $id)
    {

        $quote = Quote::find($id);

        if (!$quote) {
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [

            'estado' => 'sometimes|string|max:20|in:finalizada,cancelada',
            'tipo_entrega' => 'sometimes|string|max:20|in:plaza,envio',
            'costo_guia' => 'sometimes|nullable|numeric|min:0.01',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {

            // Actualizar cabecera
            // Toma solo los campos que vienen en la petición
            $quote->update($request->only([
                'estado',
                'tipo_entrega',
                'costo_guia',
            ]));
            
            DB::commit();

            $quote = Quote::with('detalles.supplier', 'pagos')
                ->withSum('detalles as nro_productos', 'cantidad')
                ->find($quote->id);

            return response()->json([
                'success' => true,
                'message' => 'Cotización actualizada correctamente',
                'data' => $quote
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la cotización. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar una cotización específica
     */
    public function show($id)
    {
        $quote = Quote::with('detalles.supplier', 'pagos')
            ->withSum('detalles as nro_productos', 'cantidad')
            ->find($id);

        if (!$quote) {
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $quote
        ], 200);
    }

    /**
     * Eliminación lógica
     */
    public function destroy($id)
    {
        $registro = Quote::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        // impedir eliminar cotización finalizada 
        if ($quote->estado != 'en curso') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una cotización Finalizada o Cancelada.'
            ], 422);
        }

        $registro->eliminado = true;
        $registro->save();

        return response()->json([
            'success' => true,
            'message'=>'Se ha eliminado correctamente el registro.'
        ], 200);
    }

    /**
     * Reimprimir ticket de una orden
     */
    public function ticket($id)
    {
        $orden = Quote::find($id);

        if (!$orden) {
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada.'
            ], 404);
        }

        if(!$orden->pdf){
            // Generar PDF
            $urlPdf = $this->comprobantePdf($orden->id);
            $orden->pdf = $urlPdf;
            $orden->save();
        }

        //Provisionalmente
        // $urlPdf = $this->comprobantePdf($orden->id);
        // $orden->pdf = $urlPdf;
        // $orden->save();

        return response()->json([
            'success' => true,
            'data' => $orden->pdf
        ]);
    }

    public function ticketAcortado($id)
    {
        $orden = Quote::find($id);

        if (!$orden) {
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada.'
            ], 404);
        }

        if(!$orden->pdf){
            // Generar PDF
            $urlPdf = $this->comprobantePdf($orden->id);
            $orden->pdf = $urlPdf;
            $orden->save();
        }

        // $linkAcortado = UtilitiesService::shortenUrl($orden->pdf);
        // $linkAcortado = $this->shortenUrl($orden->pdf);
        $linkAcortado = $orden->pdf;

        return response()->json([
            'success' => true,
            'data' => $linkAcortado,
            'message' => 'Link acortado generado correctamente.'
        ]);
    }

    public function comprobantePdf($id)
    {
        set_time_limit(500);

        $quote = Quote::with('detalles.supplier')
            ->withSum('detalles as nro_productos', 'cantidad')
            ->find($id);

        // $rgb = UtilitiesService::hexToRgb('#4285cb');

        $data = [
            'r' => '',
            'g' => '',
            'b' => '',
            'header' => public_path('images/ordenPlazaVestido/BARRA-SUPERIOR.jpeg'),
            'footer' => public_path('images/ordenPlazaVestido/BARRA-INFERIOR.jpeg'),

            'data' => $quote,
        ];

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('comprobantes.cotizacion', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs/comprobantes/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/comprobantes/' . $nombreArchivo);

        return $url;
    }

    public function emailQuote($quote_id)
    {
        try{

            // Usar findOrFail para evitar errores si el ID no existe
            $quote = Quote::findOrFail($quote_id);

            //Enviar Email
            $details = [
                'title' => 'Nueva Cotización '.$quote->folio,
                'body' => 'Orden: '.$quote->folio.' Cliente: '.$quote->cliente.' Total = '.$quote->total.' '.$quote->moneda
            ];

            // \Mail::to('ramirez.fred016@gmail.com')->send(new \App\Mail\NewQuoteEmail($details['title'],$details));
            \Mail::to('tonii.jaam@gmail.com')->send(new \App\Mail\NewQuoteEmail($details['title'],$details));

            return 1;
        
        }catch (\Exception $e) {
            // Loguear el error es buena práctica para saber por qué falló
            \Log::error("Error enviando email de cotización: " . $e->getMessage());
            return 0;
        }

    }

    // función auxiliar privada para calcular el estado
    private function calcularPagoEstado(Quote $quote): string
    {
        $totalPagado = $quote->total_pagado;

        if ($totalPagado <= 0) {
            return 'pendiente';
        }
        if ($totalPagado >= $quote->total) {
            return 'pagado';
        }
        return 'adelantado';
    }

    public function registrarPago(Request $request, $id)
    {
        $quote = Quote::find($id);

        if (!$quote) {
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada.'
            ], 404);
        }

        // Solo cotizaciones activas
        if ($quote->estado != 'en curso') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede registrar un pago en una cotización Finalizada o Cancelada.'
            ], 422);
        }

        // Verificar que no esté ya pagada completamente
        if ($quote->pago_estado === 'pagado') {
            return response()->json([
                'success' => false,
                'message' => 'Esta cotización ya fue pagada en su totalidad.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'tipo'        => 'required|string|in:efectivo,transferencia',
            'monto'       => 'required|numeric|min:0.01',
            'referencia'  => 'nullable|string|max:255',
            'fecha'       => 'nullable|date',
            'notas'      => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data'    => $validator->errors()
            ], 422);
        }

        if ($request->monto > $quote->saldo_restante) {
            return response()->json([
                'message' => "El monto ({$request->monto}) no puede ser mayor al saldo restante ({$quote->saldo_restante})."
            ], 422);
        }

        $pago = $quote->pagos()->create([
            'monto'      => $request->monto,
            'tipo'       => $request->tipo,
            'fecha'      => $request->fecha ?? now(),
            'referencia' => $request->referencia,
            'notas'      => $request->notas,
        ]);

        // Sincronizar pago_estado en la tabla quotes
        $quote->update(['pago_estado' => $this->calcularPagoEstado($quote)]);

        $document = $this->comprobantePdf($quote->id);
        $quote->pdf = $document;
        $quote->update([
            'pdf' => $document,
        ]);

        return response()->json([
            'message'        => 'Pago registrado correctamente.',
            'pago'           => $pago,
            'total_pagado'   => $quote->total_pagado,
            'saldo_restante' => $quote->saldo_restante,
            'pago_estado'    => $quote->fresh()->pago_estado,
        ]);
    }

    public function migrarPagosLegacy()
    {
        $quotes = Quote::whereNull('eliminado')
                    ->orWhere('eliminado', 0)
                    ->get();

        $migradas  = 0;
        $omitidas  = 0;

        foreach ($quotes as $quote) {
            // Saltar si ya tiene pagos migrados
            if ($quote->pagos()->exists()) {
                $omitidas++;
                continue;
            }

            // Adelanto
            if (!empty($quote->adelanto_monto) && $quote->adelanto_monto > 0) {
                $quote->pagos()->create([
                    'monto'      => $quote->adelanto_monto,
                    'tipo'       => $quote->adelanto_tipo,
                    'fecha'      => $quote->adelanto_fecha,
                    'referencia' => $quote->adelanto_referencia,
                ]);
            }

            // Pago restante
            if (!empty($quote->restante_monto) && $quote->restante_monto > 0) {
                $quote->pagos()->create([
                    'monto'      => $quote->restante_monto,
                    'tipo'       => $quote->restante_tipo,
                    'fecha'      => $quote->restante_fecha,
                    'referencia' => $quote->restante_referencia,
                ]);
            }

            $quote->update(['pago_estado' => $this->calcularPagoEstado($quote)]);
            $migradas++;
        }

        return response()->json([
            'message'  => 'Migración completada.',
            'migradas' => $migradas,
            'omitidas' => $omitidas,
        ]);
    }

    public function shortenUrl(string $url): string
    {
        $url = $this->prepareUrl($url);

        $services = [
            //[self::class, 'shortenIsGd'],
            [self::class, 'shortenDaGd'],
            //[self::class, 'shortenCleanUri'],
            //[self::class, 'getUpdatedTinyUrl'],
        ];

        foreach ($services as $service) {
            $short = call_user_func($service, $url);

            if ($short !== $url) {
                return $short;
            }
        }

        return $url;
    }

    private function prepareUrl(string $url): string
    {
        $url = trim($url);

        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    public function shortenDaGd(string $longUrl): string
    {
        $longUrl = self::prepareUrl($longUrl);
        $apiUrl = 'https://da.gd/s?url=' . urlencode($longUrl);

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10
        ]);

        $shortUrl = curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);

        if (!$error && $shortUrl && filter_var(trim($shortUrl), FILTER_VALIDATE_URL)) {
            return trim($shortUrl);
        }

        return $longUrl;
    }

    public function destroyPago($id)
    {
        $registro = QuotePago::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        $registro->delete();

        return response()->json([
            'success' => true,
            'message'=>'Se ha eliminado correctamente el registro.'
        ], 200);
    }

    public function setEstado($id)
    {
        $registro = Quote::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        $registro->pago_estado = 'adelantado';
        $registro->save();

        return response()->json([
            'success' => true,
            'message'=>'Se ha actualizado correctamente el registro.'
        ], 200);
    }

    public function reporteSemanal(Request $request)
    {
        $comisionPorcentaje = 0.10; // 10% variable

        $query = Quote::select('id', 'estado', 'pago_estado', 'tipo_entrega', 'created_at')
            // ->where('estado', 'finalizada')
            ->where('pago_estado', 'pagado');

        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $quotes = $query->with(['detalles.supplier'])->get();

        // Validación de resultados
        if ($quotes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay ventas disponibles en el rango de fechas seleccionado.',
                'data' => []
            ], 404);
        }

        // 1. Aplanamos y agrupamos por Supplier
        $reporte = [];
        
        foreach ($quotes as $quote) {
            foreach ($quote->detalles as $detalle) {
                $supplier = $detalle->supplier;
                $supplierId = $supplier->id;

                if (!isset($reporte[$supplierId])) {
                    $reporte[$supplierId] = [
                        'nombre' => $supplier->contacto,
                        'tienda' => $supplier->razon_social,
                        'total_compra' => 0,
                    ];
                }
                // Sumamos el total del detalle al proveedor correspondiente
                $reporte[$supplierId]['total_compra'] += (float)$detalle->total;
            }
        }

        // 2. Calculamos comisiones y totales por fila
        $dataFinal = [];
        $totalesGenerales = [
            'compra' => 0,
            'comision' => 0,
            'entregar' => 0
        ];

        foreach ($reporte as $item) {
            $descuentoComision = $item['total_compra'] * $comisionPorcentaje;
            $totalEntregar = $item['total_compra'] - $descuentoComision;

            $fila = [
                'nombre' => $item['nombre'],
                'tienda' => $item['tienda'],
                'total_compra' => round($item['total_compra'], 2),
                'comision_percent' => ($comisionPorcentaje * 100),
                'descuento_comision' => round($descuentoComision, 2),
                'total_entregar' => round($totalEntregar, 2),
            ];

            $dataFinal[] = $fila;

            // Acumulamos para el pie de tabla
            $totalesGenerales['compra'] += $fila['total_compra'];
            $totalesGenerales['comision'] += $fila['descuento_comision'];
            $totalesGenerales['entregar'] += $fila['total_entregar'];
        }

        $totalesGenerales['compra'] = round($totalesGenerales['compra'], 2);
        $totalesGenerales['comision'] = round($totalesGenerales['comision'], 2);
        $totalesGenerales['entregar'] = round($totalesGenerales['entregar'], 2);

        // $rgb = UtilitiesService::hexToRgb('#4285cb');

        $data = [
            'r' => '',
            'g' => '',
            'b' => '',
            'header' => public_path('images/ordenPlazaVestido/BARRA-SUPERIOR-REPORTE.jpeg'),
            'footer' => public_path('images/ordenPlazaVestido/BARRA-INFERIOR.jpeg'),

            'desde' => $request->input('fecha_inicio'),
            'hasta' => $request->input('fecha_fin'),

            'reporte' => $dataFinal,
            'totales_generales' => $totalesGenerales
        ];

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('reportes.reporteSemanal', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs/reportes/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/reportes/' . $nombreArchivo);

        return response()->json([
            'success' => true,
            'data' => $url
        ], 200);
    }

    public function reporteGeneral(Request $request)
    {
        $comisionPorcentaje = 0.10; // 10% variable

        // Asegúrate de incluir 'created_at' en el select
        $query = Quote::select('id', 'folio', 'estado', 'pago_estado', 'tipo_entrega', 'created_at')
            // ->where('estado', 'finalizada')
            ->where('pago_estado', 'pagado');

        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $quotes = $query->with(['detalles.supplier'])->get();

        if ($quotes->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No hay ventas.'], 404);
        }

        $reporte = [];
        
        foreach ($quotes as $quote) {
            foreach ($quote->detalles as $detalle) {
                $supplier = $detalle->supplier;
                if (!$supplier) continue;

                $supplierId = $supplier->id;
                $folio = $quote->folio;
                $key = $supplierId . '_' . $folio;

                if (!isset($reporte[$key])) {
                    $reporte[$key] = [
                        'nombre'       => $supplier->contacto,
                        'tienda'       => $supplier->razon_social,
                        'folio'        => $folio,
                        'tipo_entrega' => $quote->tipo_entrega,
                        'fecha'        => $quote->created_at, // Guardamos el objeto Carbon
                        'total_compra' => 0,
                    ];
                }
                $reporte[$key]['total_compra'] += (float)$detalle->total;
            }
        }

        $dataFinal = [];
        $totalesGenerales = ['compra' => 0, 'comision' => 0, 'entregar' => 0];

        foreach ($reporte as $item) {
            $descuentoComision = $item['total_compra'] * $comisionPorcentaje;
            $totalEntregar = $item['total_compra'] - $descuentoComision;

            $fila = [
                'nombre'             => $item['nombre'],
                'tienda'             => $item['tienda'],
                'folio'              => $item['folio'],
                'tipo_entrega'       => $item['tipo_entrega'],
                // Formateamos la fecha aquí para que Blade la reciba lista
                'fecha'              => $item['fecha']->format('d-m-Y'), 
                'total_compra'       => round($item['total_compra'], 2),
                'comision_percent'   => ($comisionPorcentaje * 100),
                'descuento_comision' => round($descuentoComision, 2),
                'total_entregar'     => round($totalEntregar, 2),
            ];

            $dataFinal[] = $fila;

            $totalesGenerales['compra']   += $fila['total_compra'];
            $totalesGenerales['comision'] += $fila['descuento_comision'];
            $totalesGenerales['entregar'] += $fila['total_entregar'];
        }

        $totalesGenerales = array_map(fn($v) => round($v, 2), $totalesGenerales);

        $data = [
            'r' => '', 'g' => '', 'b' => '',
            'header' => public_path('images/ordenPlazaVestido/BARRA-SUPERIOR-REPORTE.jpeg'),
            'footer' => public_path('images/ordenPlazaVestido/BARRA-INFERIOR.jpeg'),
            'desde'  => $request->input('fecha_inicio'),
            'hasta'  => $request->input('fecha_fin'),
            'reporte' => $dataFinal,
            'totales_generales' => $totalesGenerales
        ];

        $pdf = Pdf::loadView('reportes.reporteGeneral', $data)->setPaper('letter');
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';
        Storage::disk('public_root')->put('pdfs/reportes/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/reportes/' . $nombreArchivo);

        return response()->json([
            'success' => true,
            'data' => $url
        ], 200);
    }

    public function reporteGeneralSimple(Request $request)
    {
        $comisionPorcentaje = 0.10; // 10% variable

        // Asegúrate de incluir 'created_at' en el select
        $query = Quote::select('id', 'folio', 'estado', 'pago_estado', 'tipo_entrega', 'created_at')
            // ->where('estado', 'finalizada')
            ->where('pago_estado', 'pagado');

        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $quotes = $query->with(['detalles.supplier'])->get();

        if ($quotes->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No hay ventas disponibles en el rango de fechas seleccionado.'], 404);
        }

        // 1. Aplanamos y agrupamos por Supplier
        $reporte = [];
        
        foreach ($quotes as $quote) {
            foreach ($quote->detalles as $detalle) {
                $supplier = $detalle->supplier;
                $supplierId = $supplier->id;

                if (!isset($reporte[$supplierId])) {
                    $reporte[$supplierId] = [
                        'nombre' => $supplier->contacto,
                        'tienda' => $supplier->razon_social,
                        'total_compra' => 0,
                    ];
                }
                // Sumamos el total del detalle al proveedor correspondiente
                $reporte[$supplierId]['total_compra'] += (float)$detalle->total;
            }
        }

        // 2. Calculamos comisiones y totales por fila
        $dataFinal = [];
        $totalesGenerales = [
            'compra' => 0
        ];

        foreach ($reporte as $item) {

            $fila = [
                'nombre'             => $item['nombre'],
                'tienda'             => $item['tienda'], 
                'total_compra'       => round($item['total_compra'], 2),
            ];

            $dataFinal[] = $fila;

            $totalesGenerales['compra']   += $fila['total_compra'];
        }

        $totalesGenerales = array_map(fn($v) => round($v, 2), $totalesGenerales);

        $data = [
            'r' => '', 'g' => '', 'b' => '',
            'header' => public_path('images/ordenPlazaVestido/BARRA-SUPERIOR-REPORTE.jpeg'),
            'footer' => public_path('images/ordenPlazaVestido/BARRA-INFERIOR.jpeg'),
            'desde'  => $request->input('fecha_inicio'),
            'hasta'  => $request->input('fecha_fin'),
            'reporte' => $dataFinal,
            'totales_generales' => $totalesGenerales
        ];

        $pdf = Pdf::loadView('reportes.reporteGeneralSimple', $data)->setPaper('letter');
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';
        Storage::disk('public_root')->put('pdfs/reportes/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/reportes/' . $nombreArchivo);

        return response()->json([
            'success' => true,
            'data' => $url
        ], 200);
    }

    public function getEstadisticas(Request $request)
    {
        $user_id      = $request->user_id;
        $fecha_inicio = Carbon::parse($request->fecha_inicio)->startOfDay();
        $fecha_fin    = Carbon::parse($request->fecha_fin)->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | ADELANTADO y PAGADO — a nivel general
        |--------------------------------------------------------------------------
        */
        $statsGeneral = Quote::noEliminados()
            ->whereBetween('created_at', [$fecha_inicio, $fecha_fin])
            ->whereIn('estado', ['en curso', 'finalizada'])
            ->selectRaw("
                COUNT(CASE WHEN pago_estado = 'adelantado' THEN 1 END)               AS adelantado_cantidad,
                COALESCE(SUM(CASE WHEN pago_estado = 'adelantado' THEN total END), 0) AS adelantado_total,
                COUNT(CASE WHEN pago_estado = 'pagado'     THEN 1 END)               AS pagado_cantidad,
                COALESCE(SUM(CASE WHEN pago_estado = 'pagado'     THEN total END), 0) AS pagado_total
            ")
            ->first();

        /*
        |--------------------------------------------------------------------------
        | BASE QUERY (rango de fechas)
        |--------------------------------------------------------------------------
        */
        $baseQuery = Quote::noEliminados()
            ->where('user_id', $user_id)
            ->whereBetween('created_at', [$fecha_inicio, $fecha_fin])
            ->whereIn('estado', ['en curso', 'finalizada']);

        /*
        |--------------------------------------------------------------------------
        | ADELANTADO y PAGADO — una sola query con CASE
        |--------------------------------------------------------------------------
        */
        $stats = (clone $baseQuery)
            ->selectRaw("
                COUNT(CASE WHEN pago_estado = 'adelantado' THEN 1 END)               AS adelantado_cantidad,
                COALESCE(SUM(CASE WHEN pago_estado = 'adelantado' THEN total END), 0) AS adelantado_total,
                COUNT(CASE WHEN pago_estado = 'pagado'     THEN 1 END)               AS pagado_cantidad,
                COALESCE(SUM(CASE WHEN pago_estado = 'pagado'     THEN total END), 0) AS pagado_total
            ")
            ->first();

        /*
        |--------------------------------------------------------------------------
        | MEJOR SEMANA HISTÓRICA — Viernes a Jueves
        |
        | Restamos 4 días para que el "lunes ISO" caiga en viernes:
        |   Viernes   - 4 = Lunes  inicio de semana ISO
        |   Jueves    - 4 = Domingo → semana ISO anterior 
        |
        | Con YEARWEEK(..., 3) agrupamos correctamente y luego
        | reconstruimos el viernes exacto con ADDDATE + YEARWEEK.
        |--------------------------------------------------------------------------
        */
        $mejorSemana = Quote::noEliminados()
            ->where('user_id', $user_id)
            ->whereIn('estado', ['en curso', 'finalizada'])
            ->whereIn('pago_estado', ['adelantado', 'pagado'])
            ->whereDate('created_at', '>', '2026-03-19')
            ->selectRaw("
                YEARWEEK(DATE_SUB(created_at, INTERVAL 4 DAY), 3)  AS semana_id,
                COUNT(*)                                            AS cantidad,
                COALESCE(SUM(total), 0)                             AS monto_total,
                DATE_ADD(
                    STR_TO_DATE(
                        CONCAT(YEARWEEK(MIN(DATE_SUB(created_at, INTERVAL 4 DAY)), 3), ' Monday'),
                        '%X%V %W'
                    ),
                    INTERVAL 4 DAY
                )                                                   AS viernes_inicio
            ")
            ->groupBy('semana_id')
            ->orderByDesc('monto_total')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | RANGO DE LA MEJOR SEMANA
        |--------------------------------------------------------------------------
        */
        $rangoSemana = null;

        if ($mejorSemana) {
            $viernes = Carbon::parse($mejorSemana->viernes_inicio)->startOfDay();
            $jueves  = $viernes->copy()->addDays(6)->endOfDay();

            $rangoSemana = [
                'fecha_inicio' => $viernes->toDateTimeString(),
                'fecha_fin'    => $jueves->toDateTimeString(),
                'cantidad'     => (int)   $mejorSemana->cantidad,
                'total'        => (float) $mejorSemana->monto_total,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'success' => true,
            'data' => [
                'adelantado_general' => [
                    'cantidad' => (int)   $statsGeneral->adelantado_cantidad,
                    'total'    => (float) $statsGeneral->adelantado_total,
                ],
                'pagado_general' => [
                    'cantidad' => (int)   $statsGeneral->pagado_cantidad,
                    'total'    => (float) $statsGeneral->pagado_total,
                ],
                'adelantado' => [
                    'cantidad' => (int)   $stats->adelantado_cantidad,
                    'total'    => (float) $stats->adelantado_total,
                ],
                'pagado' => [
                    'cantidad' => (int)   $stats->pagado_cantidad,
                    'total'    => (float) $stats->pagado_total,
                ],
                'mejor_semana_historica' => $rangoSemana,
            ]
        ]);
    }

    // Función auxiliar para calcular el inicio de semana (viernes)
    public function getWeekStartFromFriday($date)
    {
        // Si es viernes, empezar desde hoy, sino ir al viernes anterior
        return $date->copy()->isFriday() 
            ? $date->copy()->startOfDay() 
            : $date->copy()->previous(Carbon::FRIDAY)->startOfDay();
    }

    public function stats($user_id)
    {

        // Semana actual
        $inicioEstaSemana = $this->getWeekStartFromFriday(Carbon::now());
        $finEstaSemana = $inicioEstaSemana->copy()->addDays(6)->endOfDay(); // Jueves

        // Semana pasada (restar 7 días al inicio y fin de la semana actual)
        $inicioSemanaPasada = $inicioEstaSemana->copy()->subWeek();
        $finSemanaPasada = $finEstaSemana->copy()->subWeek();

        // --- CONSULTA UNIFICADA ---
        $statsSemanales = Quote::noEliminados()
            ->where('user_id', $user_id)
            ->whereIn('estado', ['en curso', 'finalizada'])
            ->selectRaw("
                -- ----------------------------------------------------
                -- ESTADÍSTICAS: SEMANA EN CURSO
                -- ----------------------------------------------------
                -- Pendientes (Contador y Suma)
                COUNT(CASE WHEN created_at BETWEEN ? AND ? AND pago_estado = 'pendiente' THEN 1 END) AS esta_pendiente_cantidad,
                COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? AND pago_estado = 'pendiente' THEN total END), 0) AS esta_pendiente_total,
                

                -- Adelantados (Contador y Suma)
                COUNT(CASE WHEN created_at BETWEEN ? AND ? AND pago_estado = 'adelantado' THEN 1 END) AS esta_adelantado_cantidad,
                COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? AND pago_estado = 'adelantado' THEN total END), 0) AS esta_adelantado_total,
                
                -- Pagados (Contador y Suma)
                COUNT(CASE WHEN created_at BETWEEN ? AND ? AND pago_estado = 'pagado' THEN 1 END) AS esta_pagado_cantidad,
                COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? AND pago_estado = 'pagado' THEN total END), 0) AS esta_pagado_total,

                -- ----------------------------------------------------
                -- ESTADÍSTICAS: SEMANA PASADA
                -- ----------------------------------------------------
                -- Adelantados (Contador y Suma)
                COUNT(CASE WHEN created_at BETWEEN ? AND ? AND pago_estado = 'adelantado' THEN 1 END) AS pasada_adelantado_cantidad,
                COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? AND pago_estado = 'adelantado' THEN total END), 0) AS pasada_adelantado_total,
                
                -- Pagados (Contador y Suma)
                COUNT(CASE WHEN created_at BETWEEN ? AND ? AND pago_estado = 'pagado' THEN 1 END) AS pasada_pagado_cantidad,
                COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? AND pago_estado = 'pagado' THEN total END), 0) AS pasada_pagado_total
            ", [
                // === Parámetros Semana en Curso ===
                $inicioEstaSemana, $finEstaSemana,   // esta_pendiente_cantidad
                $inicioEstaSemana, $finEstaSemana,   // esta_pendiente_total
                $inicioEstaSemana, $finEstaSemana,   // esta_adelantado_cantidad
                $inicioEstaSemana, $finEstaSemana,   // esta_adelantado_total
                $inicioEstaSemana, $finEstaSemana,   // esta_pagado_cantidad
                $inicioEstaSemana, $finEstaSemana,   // esta_pagado_total

                // === Parámetros Semana Pasada ===
                $inicioSemanaPasada, $finSemanaPasada, // pasada_adelantado_cantidad
                $inicioSemanaPasada, $finSemanaPasada, // pasada_adelantado_total
                $inicioSemanaPasada, $finSemanaPasada, // pasada_pagado_cantidad
                $inicioSemanaPasada, $finSemanaPasada  // pasada_pagado_total
            ])
            ->first();

        /*
        |--------------------------------------------------------------------------
        | MEJOR SEMANA HISTÓRICA — Viernes a Jueves
        |
        | Restamos 4 días para que el "lunes ISO" caiga en viernes:
        |   Viernes   - 4 = Lunes  inicio de semana ISO
        |   Jueves    - 4 = Domingo → semana ISO anterior 
        |
        | Con YEARWEEK(..., 3) agrupamos correctamente y luego
        | reconstruimos el viernes exacto con ADDDATE + YEARWEEK.
        |--------------------------------------------------------------------------
        */
        $mejorSemana = Quote::noEliminados()
            ->where('user_id', $user_id)
            ->whereIn('estado', ['en curso', 'finalizada'])
            ->whereIn('pago_estado', ['adelantado', 'pagado'])
            ->whereDate('created_at', '>', '2026-03-19')
            ->selectRaw("
                DATE_SUB(
                    DATE(created_at),
                    INTERVAL ((DAYOFWEEK(created_at) + 1) % 7) DAY
                ) AS viernes_inicio,

                COUNT(*) AS cantidad,
                COALESCE(SUM(total), 0) AS monto_total
            ")
            ->groupBy('viernes_inicio')
            ->orderByDesc('monto_total')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | RANGO DE LA MEJOR SEMANA
        |--------------------------------------------------------------------------
        */
        $rangoSemana = null;

        if ($mejorSemana) {
            $viernes = Carbon::parse($mejorSemana->viernes_inicio)->startOfDay();
            $jueves  = $viernes->copy()->addDays(6)->endOfDay();

            $rangoSemana = [
                'fecha_inicio' => $viernes->toDateTimeString(),
                'fecha_fin'    => $jueves->toDateTimeString(),
                'cantidad'     => (int)   $mejorSemana->cantidad,
                'total'        => (float) $mejorSemana->monto_total,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */
        return [
            'pendiente_esta' => [
                'cantidad' => (int)   $statsSemanales->esta_pendiente_cantidad,
                'total'    => (float) $statsSemanales->esta_pendiente_total,
            ],
            'adelantado_esta' => [
                'cantidad' => (int)   $statsSemanales->esta_adelantado_cantidad,
                'total'    => (float) $statsSemanales->esta_adelantado_total,
            ],
            'pagado_esta' => [
                'cantidad' => (int)   $statsSemanales->esta_pagado_cantidad,
                'total'    => (float) $statsSemanales->esta_pagado_total,
            ],
            'adelantado_pasada' => [
                'cantidad' => (int)   $statsSemanales->pasada_adelantado_cantidad,
                'total'    => (float) $statsSemanales->pasada_adelantado_total,
            ],
            'pagado_pasada' => [
                'cantidad' => (int)   $statsSemanales->pasada_pagado_cantidad,
                'total'    => (float) $statsSemanales->pasada_pagado_total,
            ],
            'mejor_semana_historica' => $rangoSemana,
            
        ];
    }
}
