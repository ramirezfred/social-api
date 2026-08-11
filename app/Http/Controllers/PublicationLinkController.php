<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use App\Models\User;
use App\Models\PublicationLink;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PublicationLinkController extends Controller
{
    public function index(Request $request)
    {
        $query = PublicationLink::query();

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $links = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $links
        ]);
    }

    /**
     * Crear un nuevo link
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'user_id' => 'required|numeric',
            'name' => 'required|string|max:150',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors' => $validator->errors()
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

        // Token que se enviará al cliente
        $plainToken = Str::random(64);

        // Hash que se guarda en BD
        $hashedToken = hash('sha256', $plainToken);

        $link = PublicationLink::create([
            'user_id' => $request->user_id,
            'name' => $request->name,
            'token' => $hashedToken,
            'token_plain' => encrypt($plainToken),
            'status' => true,
            'starts_at' => $request->starts_at,
            'expires_at' => $request->expires_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Link generado correctamente.',
            'data' => [
                'url' => env('FRONTEND_URL') . '/#/pagessimples/publicacion/' . $plainToken,
                'link' => $link
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $registro = PublicationLink::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        $validator = Validator::make($request->all(),[
            'name' => 'sometimes|required|string|max:150',
            'status' => 'sometimes|boolean',
            'starts_at' => 'sometimes|nullable|date',
            'expires_at' => 'sometimes|nullable|date|after:starts_at',
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
        $name = $request->input('name');
        $status = $request->input('status');
        $starts_at = $request->input('starts_at');
        $expires_at = $request->input('expires_at');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        if ($name != null && $name != '')
        {
            $registro->name = $name;
            $bandera=true;
        }

        if ($starts_at != null && $starts_at != '')
        {
            $registro->starts_at = $starts_at;
            $bandera=true;
        }

        if ($expires_at != null && $expires_at != '')
        {
            $registro->expires_at = $expires_at;
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

                $link = PublicationLink::where('id', $id)->first();

                return response()->json([
                    'success' => true,
                    'message'=>'Registro editado con éxito.',
                    'data'=>$link
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

    /**
     * Validar un token
     */
    public function validateToken($token)
    {
        $hashedToken = hash('sha256', $token);

        $link = PublicationLink::where('token', $hashedToken)->first();

        if (!$link) {
            return response()->json([
                'success' => false,
                'message' => 'El enlace no existe.'
            ], 404);
        }

        if (!$link->status) {
            return response()->json([
                'success' => false,
                'message' => 'El enlace se encuentra deshabilitado.'
            ], 403);
        }

        $now = Carbon::now();

        if ($link->starts_at && $now->lt($link->starts_at)) {
            return response()->json([
                'success' => false,
                'message' => 'El enlace todavía no está disponible.'
            ], 403);
        }

        if ($link->expires_at && $now->gt($link->expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'El enlace ha expirado.'
            ], 403);
        }

        $link->increment('views');

        $link->update([
            'last_used_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token válido.',
            'data' => [
                'link_id' => $link->id,
                'name' => $link->name,
                'user_id' => $link->user_id,
            ]
        ]);
    }

    public function destroy($id)
    {
        $registro = PublicationLink::find($id);

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

    public function getUrl($id)
    {
        $link = PublicationLink::find($id);

        if (!$link) {
            return response()->json([
                'success' => false,
                'message' => 'No existe el registro.'
            ], 404);
        }


        if (!$link->token_plain) {
            return response()->json([
                'success' => false,
                'message' => 'El link no tiene token asociado.'
            ], 400);
        }


        $token = decrypt($link->token_plain);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => env('FRONTEND_URL') . '/#/pagessimples/publicacion/' . $token
            ]
        ]);
    }
}
