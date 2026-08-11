<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;
use Exception;

use App\Models\Supplier;
use App\Models\User;
use App\Models\Quote;

use Carbon\Carbon;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::noEliminados();

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('razon_social', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%")
                ->orWhere('categoria', 'like', "%$search%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $proveedores = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $proveedores,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|numeric',
            'razon_social' => 'required|string|max:255',
            'email' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:30',
            'direccion' => 'nullable|string',
            'contacto' => 'nullable|string|max:255',
            'status' => 'boolean',
            'categoria' => 'nullable|string|max:255',
            'tipo' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors' => $validator->errors(),
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

        
        if(Supplier::existeDuplicado('razon_social', $request->input('razon_social'), null)){
            return response()->json([
                'success' => false,
                'message'=>'Ya existe un proveedor con esa Razón Social.'
            ], 409);    
        }

        if ($request->filled('email')) {
            if(Supplier::existeDuplicado('email', $request->input('email'), null)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe un proveedor con ese Email.'
                ], 409);    
            }
        }

        if ($request->filled('telefono')) {
            if(Supplier::existeDuplicado('telefono', $request->input('telefono'), null)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe un proveedor con ese Teléfono.'
                ], 409);    
            }
        }

        $proveedor = Supplier::create($validator->validated());

        $proveedor = Supplier::find($proveedor->id);

        return response()->json([
            'success' => true,
            'message' => 'Registro creado con éxito.',
            'data' => $proveedor,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $registro = Supplier::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        $validator = Validator::make($request->all(),[
            'razon_social' => 'sometimes|required|string|max:255',
            'email' => "sometimes|nullable|email|max:150",
            'telefono' => 'sometimes|nullable|string',
            'direccion' => 'sometimes|nullable|string|max:150',
            'contacto' => 'sometimes|nullable|string|max:255',
            'status' => 'sometimes|boolean',
            'categoria' => 'sometimes|nullable|string|max:255',
            'envia_fotos' => 'sometimes|boolean',
            'tipo' => 'sometimes|nullable|string|max:255',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data'=>$validator->errors(),
            ],422);
        }

        // Listado de campos recibidos teóricamente.
        $razon_social = $request->input('razon_social');
        $email = $request->input('email');
        $telefono = $request->input('telefono');
        $direccion = $request->input('direccion');
        $contacto = $request->input('contacto');
        $status = $request->input('status');
        $categoria = $request->input('categoria');
        $envia_fotos = $request->input('envia_fotos');
        $tipo = $request->input('tipo');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        if ($razon_social != null && $razon_social != '')
        {
            if(Supplier::existeDuplicado('razon_social', $razon_social, $id)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe otro proveedor con esa Razón Social.'
                ], 409);    
            }

            $registro->razon_social = $razon_social;
            $bandera=true;
        }

        if ($email != null && $email != '')
        {
            if(Supplier::existeDuplicado('email', $email, $id)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe otro proveedor con ese Email.'
                ], 409);    
            }

            $registro->email = $email;
            $bandera=true;
        }

        if ($telefono != null && $telefono != '')
        {
            if(Supplier::existeDuplicado('telefono', $telefono, $id)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe otro proveedor con ese Teléfono.'
                ], 409);    
            }

            $registro->telefono = $telefono;
            $bandera=true;
        }

        if ($direccion != null && $direccion != '')
        {
            $registro->direccion = $direccion;
            $bandera=true;
        }

        if ($contacto != null && $contacto != '')
        {
            $registro->contacto = $contacto;
            $bandera=true;
        }

        if ($categoria != null && $categoria != '')
        {
            $registro->categoria = $categoria;
            $bandera=true;
        }

        if($request->has('status')){
            $registro->status = $status;
            $bandera=true;
        }

        if($request->has('envia_fotos')){
            $registro->envia_fotos = $envia_fotos;
            $bandera=true;
        }

        if ($tipo != null && $tipo != '')
        {
            $registro->tipo = $tipo;
            $bandera=true;
        }

        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($registro->save()) {

                $supplier = Supplier::where('id', $id)->first();

                return response()->json([
                    'success' => true,
                    'message'=>'Registro editado con éxito.',
                    'data'=>$supplier
                ], 200);
            }else{
                return response()->json([
                    'success' => false,
                    'message'=>'Error al actualizar el registro.'
                ], 500);
            }
            
        }
        else
        {
            return response()->json([
                'success' => false,
                'message' => 'No se ha modificado ningún dato al registro.'
            ], 400);
        }
    }

    public function destroy($id)
    {
        $registro = Supplier::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        $registro->eliminado = true;
        $registro->save();

        return response()->json([
            'success' => true,
            'message'=>'Se ha eliminado correctamente el registro.'
        ], 200);
    }

    public function getGeneradoPorSemana(Request $request, $id)
    {

        $validator = Validator::make($request->all(), [

            //'supplier_id'  => 'required|integer|exists:suppliers,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
            //'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 422);
        }

        $supplier_id = $request->input('supplier_id'); 
        $fecha_inicio = Carbon::parse($request->fecha_inicio)->startOfDay();
        $fecha_fin    = Carbon::parse($request->fecha_fin)->endOfDay();

        $generado = DB::table('quote_details')
            ->join('suppliers', 'quote_details.supplier_id', '=', 'suppliers.id')
            ->join('quotes', 'quote_details.quote_id', '=', 'quotes.id')
            ->whereIn('quotes.estado', ['en curso', 'finalizada'])
            ->where('quotes.pago_estado', 'pagado')
            ->whereBetween('quotes.created_at', [$fecha_inicio, $fecha_fin])
            ->where('quote_details.supplier_id', $id)
            ->select(
                'suppliers.id as supplier_id',
                'suppliers.razon_social',
                DB::raw('ROUND(SUM(quote_details.total), 2) as total_vendido'),
                DB::raw('ROUND(SUM(quote_details.total) * 0.90, 2) as total_deuda') // 90% para el proveedor (10% comisión)
            )
            ->groupBy('suppliers.id', 'suppliers.razon_social')
            ->first();

        if (!$generado || is_null($generado->total_vendido)) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No hay órdenes en el período seleccionado'
            ]);
        }

        $generado->total_vendido = round($generado->total_vendido, 2);
        $generado->total_deuda = round($generado->total_deuda, 2);

        return response()->json([
            'success' => true,
            'data' => $generado,
        ]);
    }

    public function getContactosByTipo($tipo)
    {

        if($tipo != 'proveedor' && $tipo != 'cliente') {
            return response()->json([
                'success' => false,
                'message' => 'Tipo inválido. Debe ser "proveedor" o "cliente".'
            ], 400);
        }

        if ($tipo == 'cliente') {

            $contactos = Quote::noEliminados()
                ->where('pago_estado', 'pagado')
                ->whereNotNull('telefono')
                ->whereRaw('TRIM(telefono) <> ""')
                ->select('id', 'cliente as nombre', 'telefono')
                ->orderBy('id')
                ->get()
                ->unique('telefono')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $this->normalizarContactos($contactos),
            ]);
        }

        $contactos = Supplier::noEliminados()
            ->where('status', true)
            ->whereNotNull('telefono')
            ->whereRaw('TRIM(telefono) <> ""')
            ->select('id', 'contacto as nombre', 'telefono', 'envia_fotos')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $this->normalizarContactos($contactos),
        ]);
    
    }

    private function normalizarTelefonoWhatsapp($telefono)
    {
        // Dejar solo números
        $telefono = preg_replace('/\D/', '', $telefono);

        // Si tiene 10 dígitos, asumir México
        if (strlen($telefono) === 10) {
            return '521' . $telefono;
        }

        // Si ya tiene 52, agregar el 1
        if (strlen($telefono) === 12 && str_starts_with($telefono, '52')) {
            return '521' . substr($telefono, 2);
        }

        // Si ya tiene 521, dejarlo igual
        if (strlen($telefono) === 13 && str_starts_with($telefono, '521')) {
            return $telefono;
        }

        // Cualquier otro caso
        return $telefono;
    }

    private function normalizarContactos($contactos)
    {
        return $contactos->map(function ($contacto) {
            $contacto->telefono = $this->normalizarTelefonoWhatsapp($contacto->telefono);
            return $contacto;
        });
    }

}