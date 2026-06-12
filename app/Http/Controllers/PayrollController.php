<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;
use Exception;

use Carbon\Carbon;

use App\Models\Payroll;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $query = Payroll::noEliminados();

        // Filtro por fechas
        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();
            $query->whereBetween('fecha', [$start, $end]);
        }

        $coleccion = $query->orderBy('fecha', 'desc')->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $coleccion,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'concepto' => 'required|string|max:255',
            'fecha' => 'required|date',
            'monto' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $registro = Payroll::create($validator->validated());

        // $payroll = Payroll::find($registro->id);

        return response()->json([
            'success' => true,
            'message' => 'Registro creado con éxito.',
            'data' => $registro,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $registro = Payroll::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        $validator = Validator::make($request->all(),[
            'concepto' => 'sometimes|required|string|max:255',
            'fecha' => 'sometimes|date',
            'monto' => 'sometimes|numeric|min:0',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors'=>$validator->errors(),
            ],422);
        }

        // Listado de campos recibidos teóricamente.
        $monto = $request->input('monto');
        $concepto = $request->input('concepto');
        $fecha = $request->input('fecha');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        if ($concepto != null && $concepto != '')
        {
            $registro->concepto = $concepto;
            $bandera=true;
        }

        if ($fecha != null && $fecha != '')
        {
            $registro->fecha = $fecha;
            $bandera=true;
        }

        if ($monto != null && $monto != '')
        {
            $registro->monto = $monto;
            $bandera=true;
        }

        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($registro->save()) {

                // $expense = Expense::where('id', $id)->first();

                return response()->json([
                    'success' => true,
                    'message'=>'Registro editado con éxito.',
                    'data'=>$registro
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
        $registro = Payroll::find($id);

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

