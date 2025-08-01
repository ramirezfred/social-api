<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Crypt;

use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\User;
use App\Models\Sistema;
use App\Models\Bot;
use App\Models\BotConfig;
use App\Models\BotCliente;
use App\Models\BotChat;

use App\Models\BotFlow;
use App\Models\BotFlowStage;
use App\Models\BotStageValidation;
use App\Models\BotCita;

use Carbon\Carbon;

use App\Http\Traits\SmsTrait;
use App\Http\Traits\ApiWhatsAppTrait;

date_default_timezone_set('America/Mexico_City');

class BotCitaController extends Controller
{
    use SmsTrait;
    use ApiWhatsAppTrait;

    public function validarToken(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            //return response()->json(['user' => $user], 200);
            return true;

        } catch (Exception $e) {

            //return true;

            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException){
                return ['error' => 'Token is Invalid'];
            } else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException){
                return ['error' => 'Token is Expired'];
            } else {
                return ['error' => 'Authorization Token not found'];
            }
        }

    }

    public function misCitas($id, Request $request)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // La cadena cifrada que se envió desde Angular
        $cadenaEncriptada = $id;

        $claveAdicional = config('app.lada_d');

        try {
            $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);
        } catch (Exception $e) {
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        $obj = BotCliente::find($cadenaDesencriptada);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        $citas = BotCita::
            where('cliente_id',$obj->id)
            ->where('status',1)
            ->get();

        return response()->json([
            'citas'=>$citas,
        ], 200);

    }

    public function misCitasFilterMes($id, Request $request)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // La cadena cifrada que se envió desde Angular
        $cadenaEncriptada = $id;

        $claveAdicional = config('app.lada_d');

        try {
            $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);
        } catch (Exception $e) {
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        $obj = BotCliente::
            select('id','bot_id','nombre',
                'telefono','empresa',
                'color_a','color_b','color_c','logo')
            ->find($cadenaDesencriptada);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        $citas = BotCita::
            select('id','bot_id','cliente_id','nombre',
                'telefono','motivo','fecha','day',
                'month','year','hora','hour',
                'minutes','status','status_sms')
            ->where('cliente_id',$obj->id)
            ->where('status',1)
            ->where('month',$request->input('month'))
            ->where('year',$request->input('year'))
            ->get();

        $agenda = [];
        for ($i=1; $i <= 31; $i++) { 
            $resul = (object) [
                'dia' => $i,
                'citas' => [],
            ];
            array_push($agenda,$resul);
        }

        for ($i=0; $i < count($citas); $i++) { 
            array_push($agenda[$citas[$i]->day - 1]->citas,$citas[$i]);
        }

        for ($i=0; $i < count($agenda); $i++) { 
            // Ordenar el array $citas por "hour" y "minutes"
            usort($agenda[$i]->citas, [$this, 'compararPorHora']);
        }

        return response()->json([
            'cliente'=>$obj,
            'agenda'=>$agenda,
            //'citas'=>$citas,
        ], 200);

    }

    // Función de comparación para ordenar por "hour" y "minutes"
    public function compararPorHora($cita1, $cita2) {
        if ($cita1["hour"] === $cita2["hour"]) {
            return $cita1["minutes"] - $cita2["minutes"];
        }
        return $cita1["hour"] - $cita2["hour"];
    }

    public function notificarCitas()
    {
        set_time_limit(500);

        //fecha actual
        $date = Carbon::now();
        $day = $date->day;
        $month = $date->month;
        $year = $date->year;

        $hora = $date->hour;
        $minutos = $date->minute;

        if($hora == 0){
            $hora = 23;
        }/*else{
            $hora = $hora - 1;
        }*/

        // Crea dos objetos Carbon que representan las horas que deseas comparar
        $hora1 = Carbon::createFromTimeString($hora.':'.$minutos);
        
        $citasA = BotCita::
            with(['cliente' => function ($query) {
                $query->select('id','nombre','telefono','empresa');
            }])
            ->where(function ($query) {
                $query
                    ->whereNull('status_sms')
                    ->orWhere('status_sms',0);
            })
            ->where('day', $day)
            ->where('month', $month)
            ->where('year', $year)
            ->where('status', 1)
            ->get();

        $citas = [];
        $minutos_compare = 120;
        for ($i=0; $i < count($citasA); $i++) {

            $hora2 = Carbon::parse($citasA[$i]->hour.':'.$citasA[$i]->minutes);
            //hora1 <= hora2 ?
            if ($hora1->lessThanOrEqualTo($hora2)) {
                $diferencia_en_minutos = $hora1->diffInMinutes($hora2);
                if($diferencia_en_minutos <= $minutos_compare){
                    array_push($citas,$citasA[$i]);
                }
            }

        }

        for ($i=0; $i < count($citas); $i++) { 

            $short_link = $this->shortenURL('https://wa.me/'.$citas[$i]->cliente->telefono);
            $short_link = str_replace("https://", "", $short_link);

            $partes = explode(' ', $citas[$i]->nombre);
            $nombre = $partes[0];
            
            //$message = '¡Recordatorio! {{nombre}} tienes cita programada para hoy a las {{hora}} con {{short_link}} por favor, asiste. ¡Nos vemos!';
            $message = '¡Atención! {{nombre}}, tienes cita a las {{hora}} hoy con {{empresa}} más info aquí: {{short_link}}';

            $message = str_replace("{{nombre}}", $nombre, $message);
            $message = str_replace("{{hora}}", $citas[$i]->hora, $message);
            $message = str_replace("{{empresa}}", $citas[$i]->cliente->empresa, $message);
            $message = str_replace("{{telefono}}", $citas[$i]->cliente->telefono, $message);
            $message = str_replace("{{short_link}}", $short_link, $message);

            $response = $this->enviarSMS($citas[$i]->telefono,$message);

            $citas[$i]->status_sms = 1;
            $citas[$i]->save();

            $citas[$i]->message = $message;
            //$citas[$i]->response = $response;
        }

        $this->notificarNewClientes();

        return response()->json([
            'message' => count($citas).' SMS enviados',
            //'citas' => $citas,
            //'link' => $link,
        ], 200);

    }

    public function shortenURL($url)
    {
        $apiUrl = 'https://is.gd/api.php';
        $response = file_get_contents($apiUrl . '?longurl=' . urlencode($url));

        // Verificar si se obtuvo una respuesta válida
        if (filter_var($response, FILTER_VALIDATE_URL)) {
            return $response; // Devuelve el enlace acortado
        } else {
            // Manejar el error en caso de no obtener un enlace acortado válido
            return $url; // Devuelve la URL original sin acortar
        }
    }

    public function enviarSMS($number, $message)
    {

        //$message = "¡Atención! Antonio, tienes cita a las 04:00 PM hoy con ULA más info aquí: is.gd\CAtjuC";

        $url = 'https://api.goopy.app/internow_social/enviar_sms';

        //Armando la peticion cURL        
        $fields = array(
            //'number' => '5527399115',
            'number' => $number,
            'message' => $message,
        ); 
            
        $fields = json_encode($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            //"Authorization: Bearer ".$token,
            "Content-Type: application/json"
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return $err;
        } else {
            return $response;
        }
    }

    public function store(Request $request, $cliente_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'nombre'=>'required|string',
            'telefono'=>'required|numeric|digits:10',
            'motivo'=>'required|string',
            'fecha'=>'required|string',
            'hora'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        // Comprobamos si el cliente que nos están pasando existe o no.
        $cliente=BotCliente::find($cliente_id);
        if (!$cliente)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado.'], 404);
        }   
        
        // Listado de campos recibidos teóricamente.
        $nombre=$request->input('nombre');
        $telefono=$request->input('telefono');
        $motivo=$request->input('motivo');
        $fecha=$request->input('fecha');
        $hora=$request->input('hora');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Validaciones.

        if(strlen($nombre) > 30){
            return response()->json(['error'=>'El nombre del cliente debe tener máximo 30 caracteres.'], 409);
        }

        if(strlen($telefono) != 10){
            return response()->json(['error'=>'El teléfono del cliente debe tener 10 caracteres.'], 409);
        }

        if(strlen($motivo) > 200){
            return response()->json(['error'=>'El motivo de la cita debe tener máximo 200 caracteres.'], 409);
        }

        // Convierte la fecha en formato "dd/mm/yyyy" a un objeto Carbon
        $fechaCarbon = Carbon::createFromFormat('d/m/Y', $fecha);

        // Obtén la fecha actual en formato Carbon
        $fechaActual = Carbon::now();

        // Compara si la fecha es mayor o igual que la fecha actual
        if ($fechaCarbon->isSameDay($fechaActual) || $fechaCarbon->greaterThan($fechaActual)) {

            $partes = explode('/', $fecha);
            $day = $partes[0];
            $month = $partes[1];
            $year = $partes[2];

        } else {
            return response()->json([
                'error'=>'Debes seleccionar una fecha mayor o igual a la fecha actual.',
                'fecha' => $fecha,
                'fechaActual' => $fechaActual,
                'fechaCarbon' => $fechaCarbon,
            ], 409);
        }

        $timestamp = strtotime($hora);
        $hora_24h = date('H:i', $timestamp);
        $partes = explode(':', $hora_24h);
        $hour = $partes[0];
        $minutes = $partes[1];
       
        // Almacenamos en la base de datos el registro.
        if ($nuevoObj=BotCita::create([
            'bot_id'=>$cliente->bot_id,
            'cliente_id'=>$cliente_id,
            'nombre'=>$nombre,
            'telefono'=>$telefono,
            'email'=>null,
            'motivo' => $motivo,
            'fecha'=>$fecha,
            'day'=>$day,
            'month'=>$month,
            'year'=>$year,
            'hora'=>$hora,
            'hour' => $hour,
            'minutes' => $minutes,
            'status'=>1,
            'status_sms'=>0,
            
        ])) {
            return response()->json([
                'message'=>'Cita agenda con éxito.',
                'cita'=>$nuevoObj
            ], 200);
        }else{
            return response()->json(['error'=>'Error al agendar la cita.'], 500);
        } 
    }

    public function update(Request $request, $id)
    {
        // Comprobamos si la cita que nos están pasando existe o no.
        $cita=BotCita::find($id);

        if (!$cita)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cita no encontrada.'], 404);
        }    
        
        // Listado de campos recibidos teóricamente.
        $nombre=$request->input('nombre');
        $telefono=$request->input('telefono');
        $motivo=$request->input('motivo');
        $fecha=$request->input('fecha');
        $hora=$request->input('hora');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if ($nombre != null && $nombre!='')
        {
            if(strlen($nombre) > 30){
                return response()->json(['error'=>'El nombre del cliente debe tener máximo 30 caracteres.'], 409);
            }

            $cita->nombre = $nombre;
            $bandera=true;
        }

        if ($telefono != null && $telefono!='')
        {
            if(strlen($telefono) != 10){
                return response()->json(['error'=>'El teléfono del cliente debe tener 10 caracteres.'], 409);
            }

            $cita->telefono = $telefono;
            $bandera=true;
        }

        if ($motivo != null && $motivo!='')
        {
            if(strlen($motivo) > 200){
                return response()->json(['error'=>'El motivo de la cita debe tener máximo 200 caracteres.'], 409);
            }

            $cita->motivo = $motivo;
            $bandera=true;
        }

        if ($fecha != null && $fecha!='' && $fecha!=$cita->fecha)
        {
            // Convierte la fecha en formato "dd/mm/yyyy" a un objeto Carbon
            $fechaCarbon = Carbon::createFromFormat('d/m/Y', $fecha);

            // Obtén la fecha actual en formato Carbon
            $fechaActual = Carbon::now();

            // Compara si la fecha es mayor o igual que la fecha actual
            if ($fechaCarbon->isSameDay($fechaActual) || $fechaCarbon->greaterThan($fechaActual)) {

                $partes = explode('/', $fecha);
                $day = $partes[0];
                $month = $partes[1];
                $year = $partes[2];

                $cita->fecha = $fecha;
                $cita->day = $day;
                $cita->month = $month;
                $cita->year = $year;
                $cita->status_sms = 0;
                $bandera=true;
            } else {
                return response()->json([
                    'error'=>'Debes seleccionar una fecha mayor o igual a la fecha actual.',
                    'fecha' => $fecha,
                    'fechaActual' => $fechaActual,
                    'fechaCarbon' => $fechaCarbon,
                ], 409);
            }

        }

        if ($hora != null && $hora!='' && $hora!=$cita->hora)
        {
            $timestamp = strtotime($hora);
            $hora_24h = date('H:i', $timestamp);
            $partes = explode(':', $hora_24h);
            $hour = $partes[0];
            $minutes = $partes[1];

            $cita->hora = $hora;
            $cita->hour = $hour;
            $cita->minutes = $minutes;
            $bandera=true;
        }
       
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($cita->save()) {
                return response()->json(['message'=>'Cita actualizada.',
                 'cita'=>$cita], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar la cita.'], 500);
            }           
        }
        else
        {
            // Se devuelve un array error con los error encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún a la cita.'],500);
        }
    }

    public function destroy($id)
    {
        $obj = BotCita::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cita no encontrada'], 404);
        }

        // Eliminamos la marca
        $obj->delete();

        return response()->json(['message'=>'Se ha eliminado correctamente la cita.'], 200);
    }

    public function notificarNewClientes(){

        $clientes = BotCliente::select('id','telefono','empresa','count_alertas','fecha_alerta')
            ->where(function ($query) {
               $query
                   ->whereNull('count_alertas')
                   ->orWhere('count_alertas', '<', 3);
               })
            ->whereNotNull('fecha_alerta')
            ->where(function ($query) {
               $query
                   ->whereNull('empresa')
                   ->orWhere('empresa','');
               })
            //->where('id',2)
            ->get();

        //fecha actual
        $date = Carbon::now();
        //$date = Carbon::create(2023, 12, 5, 12, 00);

        for ($i=0; $i < count($clientes); $i++) { 
            $diferencia = $date->diffInHours($clientes[$i]->fecha_alerta);

            if($diferencia >= 7){

                $body = '¿Ya has completado el proceso de configuración de tu marca? Al hacerlo, podrás acceder a todas mis habilidades y aprovechar al máximo mis posibilidades.';

                $resp = $this->_messageText(2,$clientes[$i]->telefono,$body);

                $user_token=User::find(56);
                $token = JWTAuth::fromUser($user_token);

                $claveAdicional = config('app.lada_d');

                $cadenaEncriptada = Crypt::encrypt($clientes[$i]->id, $claveAdicional);

                $link = 'https://social.internow.com.mx/#/config-cliente-bot/'.$cadenaEncriptada.'/'.$token;

                $short_link = $this->shortenURL($link);

                $message = 'Ingresa en el siguiente enlace para configurar tu marca:

{{short_link}}';

                $message = str_replace("{{short_link}}", $short_link, $message);

                $resp = $this->_messageText(2,$clientes[$i]->telefono,$message);

                $clientes[$i]->fecha_alerta = $date;
                $clientes[$i]->count_alertas = $clientes[$i]->count_alertas + 1;
                $clientes[$i]->save();

            }
        }

        return response()->json([
            'date'=>$date,
            //'clientes'=>$clientes
        ], 200);

    }
}
