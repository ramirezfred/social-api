<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;
use Exception;

use App\Models\Supplier;

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

        $proveedores = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $proveedores,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'razon_social' => 'required|string|max:255',
            'email' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:30',
            'direccion' => 'nullable|string',
            'contacto' => 'nullable|string|max:255',
            'status' => 'boolean',
            'categoria' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors' => $validator->errors(),
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
}
