<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

use DB;

use JWTAuth;
use Exception;
use Tymon\JWTAuth\Exceptions\JWTException;
//use Tymon\JWTAuth\Facades\JWTAuth;

use Illuminate\Support\Facades\Auth;

use App\Models\User;

trait UtilitiesTrait
{
    
    public static function _autenticarClienteCrud($request)
    {

        try{ 
            //$currentUser = JWTAuth::toUser($request->input('token'));
            $currentUser = JWTAuth::parseToken()->authenticate();

            if ($currentUser) { 

                $usuario = User::find($currentUser->id);
                if(count($usuario)==0){
                    return [
                        'status'=>500,
                        'error'=>'Usuario no encontrado'
                    ];
                }

                if ($usuario->tipo != 2) {
                    return [
                        'status'=>401,
                        'error'=>'Permisos inválidos.'
                    ];
                } 

                if ($usuario->status != 1) {
                    return [
                        'status'=>401,
                        'error'=>'Usuario inhabilitado. Háblanos a nuetro WhatsApp.'
                    ];
                }

                return [
                    'status'=>200,
                    'user'=>$usuario
                ];

            }else{    
                return [
                    'status'=>400,
                    'error'=>'Usuario no autenticado.'
                ];         
            }

        } catch (Exception $e) {
            return [
                'status'=>400,
                'error'=>'Error al autenticar.'
            ];
        }
    }

    public static function _autenticarAdminCrud($request)
    {

        try{ 
            
            //$currentUser = JWTAuth::toUser($request->input('token'));
            $currentUser = JWTAuth::parseToken()->authenticate();
            //$currentUser = auth()->user();

            // return [
            //     'status'=>400,
            //     'error'=>$currentUser
            // ];

            if ($currentUser) { 

                $usuario = User::find($currentUser->id);
                if(count($usuario)==0){
                    return [
                        'status'=>500,
                        'error'=>'Usuario no encontrado'
                    ];
                }

                if ($usuario->tipo != 1) {
                    return [
                        'status'=>401,
                        'error'=>'Permisos inválidos.'
                    ];
                } 

                /*if ($usuario->status != 1) {
                    return [
                        'status'=>401,
                        'error'=>'Usuario inhabilitado.'
                    ];
                }*/

                return [
                    'status'=>200,
                    'user'=>$usuario
                ];

            }else{    
                return [
                    'status'=>400,
                    'error'=>'Usuario no autenticado.'
                ];         
            }

        } catch (Exception $e) {
            return [
                'status'=>400,
                'error'=>'Error al autenticar.'
            ];
        }
    }

}
