<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Familia;
use App\Models\Cuenta;
use App\Models\Obra;
use App\Models\Partida;
use App\Models\Movimiento;

//use Hash;
use DB;
//use Validator;

use DateTime;
use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

class DashboardController extends Controller
{
    /*Retorna los contadores del mes actual*/
    public function contadores()
    {
        $dia_actual = date("d"); //j  Día del mes sin ceros iniciales 1 a 31
                                //d Día del mes, 2 dígitos con ceros iniciales  01 a 31
        $mes_actual = date("m");
        $anio_actual = date("Y");

        /*return response()->json(['date'=>date("Y-m-d H:i:s"),
            'dia_actual' => date("d"),
        'mes_actual' => date("m"),
        'anio_actual' => date("Y")], 200);*/


        $cuentas = Cuenta::
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->count();

        $obras = Obra::
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->count();

        $partidas = Partida::
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->count();

        $movimientos = Movimiento::
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            where(DB::raw('MONTH(fecha)'),$mes_actual)
            ->where(DB::raw('YEAR(fecha)'),$anio_actual)
            ->count();


        return response()->json([
            'cuentas'=>$cuentas,
            'obras'=>$obras,
            'partidas'=>$partidas,
            'movimientos'=>$movimientos,
        ], 200);

    }
}
