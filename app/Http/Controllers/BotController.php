<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Crypt;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

use DateTime;
use Carbon\Carbon;

use App\Models\User;
use App\Models\Bot;

use App\Http\Traits\ApiWhatsAppTrait;

date_default_timezone_set('America/Mexico_City');

class BotController extends Controller
{
    use ApiWhatsAppTrait;

    public function index()
    {
        $objs = Bot::all();

        return response()->json(['bots'=>$objs], 200);
    }

    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            //'email'=>'required|string|email|unique:users,email',
            'email'=>'required|string',
            'password'=>'required|string',
            //'telefono'=>'required|numeric|digits:10',
            'telefono'=>'required|string',
            'chat_nombre'=>'required|string',
            'chat_telefono'=>'required|string',
            'number_id'=>'required|string',
            'access_token'=>'required|string',

        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux2 = User::where('email', $request->input('email'))->get();
        if(count($aux2)!=0){
            return response()->json(['error'=>'Ya existe un usuario con esas credenciales.'], 409);    
        }

        $chat_telefono = Bot::where('telefono', $request->input('chat_telefono'))->get();
        if(count($chat_telefono)!=0){
            return response()->json(['error'=>'Ya existe un Bot con ese teléfono.'], 409);    
        }

        $number_id = Bot::where('number_id', $request->input('number_id'))->get();
        if(count($number_id)!=0){
            return response()->json(['error'=>'Ya existe un Bot con ese number_id.'], 409);    
        }

        $whatsapp_id = Bot::where('whatsapp_id', $request->input('whatsapp_id'))->get();
        if(count($whatsapp_id)!=0){
            return response()->json(['error'=>'Ya existe un Bot con ese whatsapp_id.'], 409);    
        }

        /*Primero creo una instancia en la tabla usuarios*/
        $usuario = new User;
        $usuario->tipo = 3; //cliente chatbot
        $usuario->status = 1;
        $usuario->email = $request->input('email');
        $usuario->password = Hash::make($request->input('password'));
        $usuario->telefono = $request->input('telefono');
        
        if($usuario->save()){

            //Generar código alatorio
            $salt = 'abcdefghijklmnopqrstuvwxyz1234567890';
            $rand = '';
            $i = 0;
            $length = 5;
            while ($i < $length) {
                //Loop hasta que el string aleatorio contenga la longitud ingresada.
                $num = rand() % strlen($salt);
                $tmp = substr($salt, $num, 1);
                $rand = $rand . $tmp;
                $i++;
            }
            $codigo = $rand;

            $cadena = $request->input('access_token').$codigo;

            $claveAdicional = config('app.lada_c');

            $cadenaEncriptada = Crypt::encrypt($cadena, $claveAdicional);

            if($nuevoObj=Bot::create([
                'user_id'=>$usuario->id,
                'status'=>1,
                'nombre'=>$request->input('chat_nombre'),
                'telefono'=>$request->input('chat_telefono'),
                'number_id'=>$request->input('number_id'),
                'whatsapp_id'=>$request->input('whatsapp_id'),
                'access_token'=>$cadenaEncriptada,
                
            ])){

                return response()->json([
                    'message'=>'Bot creado con éxito.',
                    'usuario'=>$usuario,
                    'bot'=>$nuevoObj,
                ], 200);

            }else{
                return response()->json(['error'=>'Error al crear el bot.'], 500);
            }
           
        }else{
            return response()->json(['error'=>'Error al crear el usuario.'], 500);
        }
    }

    public function messageText(Request $request)
    {
        //$body = 'Termina tu proceso de configurar tu marca para usar todas mis habilidades. 🤗';

        //$resp = $this->_messageText($request->input('bot_id'),$request->input('to'),$body);
        $resp = $this->_messageText($request->input('bot_id'),$request->input('to'),$request->input('body'));

        // $type_component = 'body';
        // $parameters = array(
        //     array(
        //         'type' => 'text',
        //         'text' => 'Antonio'
        //     )
        // );

        // $resp = $this->_messageTemplateParameters($request->input('bot_id'),$request->input('to'),$template,$language,$type_component,$parameters);

        if ($resp['status'] == 200) {
            return response()->json([
                'whatsapp'=>$resp
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'whatsapp'=>$resp['whatsapp']
            ], $resp['status']); 
        }
    }

    public function encryptBot($cadena)
    {
        //Generar código alatorio
        $salt = 'abcdefghijklmnopqrstuvwxyz1234567890';
        $rand = '';
        $i = 0;
        $length = 5;
        while ($i < $length) {
            //Loop hasta que el string aleatorio contenga la longitud ingresada.
            $num = rand() % strlen($salt);
            $tmp = substr($salt, $num, 1);
            $rand = $rand . $tmp;
            $i++;
        }
        $codigo = $rand;

        $cadena = $cadena.$codigo;

        //$claveAdicional = config('app.lada_c');
        $claveAdicional = config('app.lada_b');

        $cadenaEncriptada = Crypt::encrypt($cadena, $claveAdicional);

        $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

        return response()->json([
            'cadenaEncriptada'=>$cadenaEncriptada,
            //'cadenaDesencriptada'=>$cadenaDesencriptada,
        ], 200);
    }

    public function updateTokenBot(Request $request)
    {
        // Comprobamos si lo que nos están pasando existe o no.
        $bot = Bot::find(2);

        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Bot no encontrado'], 404);
        }

        // Listado de campos recibidos teóricamente.
        $access_token=$request->input('access_token');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.

        if ($access_token != null && $access_token!='')
        {
            //Generar código alatorio
            $salt = 'abcdefghijklmnopqrstuvwxyz1234567890';
            $rand = '';
            $i = 0;
            $length = 5;
            while ($i < $length) {
                //Loop hasta que el string aleatorio contenga la longitud ingresada.
                $num = rand() % strlen($salt);
                $tmp = substr($salt, $num, 1);
                $rand = $rand . $tmp;
                $i++;
            }
            $codigo = $rand;

            $cadena = $access_token;

            $cadena = $cadena.$codigo;

            //$claveAdicional = config('app.lada_c');
            $claveAdicional = config('app.lada_b');

            $cadenaEncriptada = Crypt::encrypt($cadena, $claveAdicional);

            $bot->access_token = $cadenaEncriptada;

            $date = Carbon::now();
            $newDate = $date->addDays(1);

            $bot->fecha_token = $newDate;

            $bandera=true;
        }
        
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($bot->save()) {
                
                return response()->json([
                    'message'=>'Bot configurado con éxito.',
                    'access_token'=>$bot->access_token
                ], 200);
            }else{
                return response()->json(['error'=>'Error al configurar el bot.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al bot.'],409);
        }
    }

    public function getTokenBot()
    {
        // Comprobamos si lo que nos están pasando existe o no.
        $bot = Bot::find(2);

        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Bot no encontrado'], 404);
        }

        return response()->json([
            'access_token'=>$bot->access_token
        ], 200);
    }

    public function alertToken()
    {
        $bot = Bot::find(2);

        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Bot no encontrado'], 404);
        }

        //fecha actual
        $date = Carbon::now();
        $hora = $date->hour;
        $minutos = $date->minute;
        $dia = $date->day;
        $mes = $date->month;
        $anio = $date->year;
        //$date2 = Carbon::create(2023, 12, 6, 12, 00);

        // Crea dos objetos Carbon que representan las horas que deseas comparar
        $hora1 = Carbon::createFromTimeString($hora.':'.$minutos);

        // Dividir la cadena en partes utilizando el espacio como separador
        $dateParts = explode(" ", $bot->fecha_token);
      

        if($bot->fecha_token != '' && $bot->fecha_token != null){

            // Dividir la cadena en partes utilizando el espacio como separador
            $dateParts = explode(" ", $bot->fecha_token);

            $fechaParts = explode("-", $dateParts[0]);
            if(

                $anio == $fechaParts[0] &&
                $mes == $fechaParts[1] &&
                $dia == $fechaParts[2]

            ){

                // Obtener la parte de la hora y dividirla en horas, minutos y segundos
                $timeParts = explode(":", $dateParts[1]);

                // Acceder a las partes específicas
                $hour = $timeParts[0];
                $minute = $timeParts[1];

                $hora2 = Carbon::createFromTimeString($hour.':'.$minute);

                if ($hora2->greaterThanOrEqualTo($hora1)) {
                    
                    $diferencia_en_minutos = $hora2->diffInMinutes($hora1);

                    if($diferencia_en_minutos <= 30){

                        //Enviar WhatsApp
                        // $template = 'hello_world';
                        // $language = 'en_US';

                        $template = 'recordatorio_token';
                        $language = 'es_MX';

                        $telefonos = [
                            '5215527399115'
                        ];

                        for ($i=0; $i < count($telefonos); $i++) { 

                            $type_component = 'body';
                            $parameters = array(
                                array(
                                    'type' => 'text',
                                    'text' => 'InterNow'
                                )
                            );

                            $resp = $this->_messageTemplateParameters(2,$telefonos[$i],$template,$language,$type_component,$parameters);
                        }

                        //Enviar Email
                        $details = [
                            'title' => 'Actualizar Token',
                            'body' => 'Hola, por favor actualiza el token API de IA InterNow.'
                        ];

                        $correos = [
                            'Tonii.jaam@gmail.com',
                            'Karlasanchez041193@gmail.com',
                            'Hola@internow.com.mx'

                        ];

                        for ($i=0; $i < count($correos); $i++) { 
                            \Mail::to($correos[$i])->send(new \App\Mail\NotificacionEmail($details));
                        } 

                    }

                }

            }


        }  

        return 1;    
        
    }
}
