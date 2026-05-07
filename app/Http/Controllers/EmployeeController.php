<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use App\Models\User;

class EmployeeController extends Controller
{
    public function index()
    {
        $coleccion = User::noEliminados()
            ->where('tipo', 4)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'=>$coleccion
        ], 200);
    }

    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'email'=>'required|string|max:255',
            'password'=>'required|string|max:255',
            'nombre'=>'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 422);
        }

        if(User::existeDuplicado('email', $request->input('email'), null)){
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un usuario con ese correo.'
            ], 409);    
        }

        /*Primero creo una instancia en la tabla usuarios*/
        $usuario = new User;
        $usuario->tipo = 4; //4=Vendedor
        $usuario->status = 1;
        $usuario->email = $request->input('email');
        $usuario->password = Hash::make($request->input('password'));
        $usuario->nombre = $request->input('nombre');
        
        if($usuario->save()){
           return response()->json([
                'success' => true,
                'message'=>'Usuario creado con éxito.',
                'data'=>$usuario
            ], 201);
        }else{
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el usuario.'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'nombre' => 'nullable|string|max:255',
            'status' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación.',
                'data' => $validator->errors()
            ], 422);
        }

        // Comprobamos si lo que nos están pasando existe o no.

        $usuario = User::find($id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message' => 'No existe el usuario con id '.$id
            ], 404);
        }

        if($usuario->tipo == 1){
            return response()->json([
                'success' => false,
                'message' => 'Permisos inválidos.'
            ], 401);
        }

        // Listado de campos recibidos teóricamente.
        $email=$request->input('email'); 
        $password=$request->input('password'); 
        $nombre=$request->input('nombre'); 
        $status=$request->input('status'); 

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.
        if ($email != null && $email!='')
        {
            if(User::existeDuplicado('email', $request->input('email'), $id)){
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otro usuario con ese correo.'
                ], 422);    
            }

            $usuario->email = $email;
            $bandera=true;
        }

        if ($password != null && $password!='')
        {
            $usuario->password = Hash::make($request->input('password'));
            $bandera=true;
        }

        if ($nombre != null && $nombre != '')
        {
            $usuario->nombre = $nombre;
            $bandera=true;
        }

        if (!is_null($status)) {
            $usuario->status = $status;
            $bandera = true;
        }

        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($usuario->save()) {
                return response()->json([
                    'success' => true,
                    'message'=>'Usuario editado con éxito.',
                    'data'=>$usuario
                ], 200);
            }else{
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar el usuario.'
                ], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json([
                'success' => false,
                'message' => 'No se ha modificado ningún dato al usuario.'
            ], 422);
        }
    }

    public function updatePassword(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $usuario = User::find($id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message' => 'No existe el usuario con id '.$id
            ], 404);
        }


        // Listado de campos recibidos teóricamente.
        $password=$request->input('password'); 

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.

        if ($password != null && $password!='')
        {
            $usuario->password = Hash::make($request->input('password'));
            $bandera=true;
        }


        if ($bandera)
        {

            // Almacenamos en la base de datos el registro.
            if ($usuario->save()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Usuario editado con éxito.',
                    'data' => $usuario
                ], 200);
            }else{
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar el usuario.'
                ], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json([
                'success' => false,
                'message' => 'No se ha modificado ningún dato al usuario.'
            ], 422);
        }
    }

    public function destroy($id)
    {
        $usuario=User::find($id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.'
            ], 404);
        } 

        // Eliminamos el usuario
        //$usuario->delete();

        if($usuario->tipo == 1){
            return response()->json([
                'success' => false,
                'message' => 'Permisos inválidos.'
            ], 401);
        }

        $usuario->eliminado = true;
        $usuario->save();

        return response()->json([
            'success' => true,
            'message' => 'Se ha eliminado correctamente el usuario.'
        ], 200);
    }

   
}
