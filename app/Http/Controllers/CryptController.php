<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Crypt;

use Illuminate\Http\Request;

class CryptController extends Controller
{
    public function encrypt($cadena)
    {
        $claveAdicional = config('app.lada_a');

        $cadenaEncriptada = Crypt::encrypt($cadena, $claveAdicional);

        $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

        return response()->json([
            'cadenaEncriptada'=>$cadenaEncriptada,
            //'cadenaDesencriptada'=>$cadenaDesencriptada,
        ], 200);
    }

    public function decrypt($cadenaEncriptada)
    {
        $claveAdicional = config('app.lada_a');

        $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

        return response()->json([
            'cadenaDesencriptada'=>$cadenaDesencriptada,
        ], 200);
    }
}
