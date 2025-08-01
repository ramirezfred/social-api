<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Crypt;

use App\Models\User;
use App\Models\Sistema;
use App\Models\Bot;
use App\Models\BotCliente;
use App\Models\BotChat;

//use Hash;
use DB;
//use Validator;
use Exception;


use Carbon\Carbon;

use App\Http\Traits\ApiWhatsAppTrait;
use App\Http\Traits\ApiTextCortexTrait;
use App\Http\Traits\ApiOpenAiTrait;

date_default_timezone_set('America/Mexico_City');

class BotRespuestaController extends Controller
{
    use ApiWhatsAppTrait;
    use ApiTextCortexTrait;
    use ApiOpenAiTrait;

    public function getMensajesSinProcesar()
    {
        $mensajes = BotChat::
            select('id','bot_id','cliente_id','text','autor','status')
            ->where('status', 0)
            ->where('autor', 1)
            ->get();

        $clientes_ids = [];

        for ($i=0; $i < count($mensajes); $i++) { 
            $esta = false;
            for ($j=0; $j < count($clientes_ids); $j++) { 
                if($mensajes[$i]->cliente_id == $clientes_ids[$j]){
                    $esta = true;
                }
            }
            if(!$esta){
                array_push($clientes_ids,$mensajes[$i]->cliente_id);
            }
        }

        $clientes = BotCliente::
            select('id','bot_id','nombre','telefono')
            ->whereIn('id', $clientes_ids)
            ->get();

        for ($i=0; $i < count($clientes); $i++) {
            $msjs = []; 
            for ($j=0; $j < count($mensajes); $j++) { 
                if($clientes[$i]->id == $mensajes[$j]->cliente_id){
                    array_push($msjs,$mensajes[$j]->text);
                }
            }
            $clientes[$i]->mensajes = $msjs;
        }

        for ($i=0; $i < count($clientes); $i++) { 

            //$respA = $this->_palabraClave($clientes[$i]->bot_id,$clientes[$i]->mensajes);
            $respA = $this->_davinciPalabraClaveBot($clientes[$i]->bot_id,$clientes[$i]->mensajes);

            if ($respA['status'] == 200) {

                $clientes[$i]->palabra_clave = $respA['text'];

                //$respB = $this->_respuesta($clientes[$i]->bot_id,$clientes[$i]->mensajes,$clientes[$i]->palabra_clave);
                $respB = $this->_davinciRespuestaBot($clientes[$i]->bot_id,$clientes[$i]->mensajes,$clientes[$i]->palabra_clave);
                if ($respB['status'] == 200) {

                    $clientes[$i]->respuesta = $respB['text'];
                    $message = $respB['text'];

                    $respC = $this->_messageText($clientes[$i]->bot_id,$clientes[$i]->telefono,$message);
                    if ($respC['status'] == 200) {

                        DB::table('bot_chats')
                            ->where('bot_id', $clientes[$i]->bot_id)
                            ->where('cliente_id', $clientes[$i]->id)
                            ->update(['status' => 1]);

                        $cliente_id = $this->getClienteId($clientes[$i]->bot_id,$clientes[$i]->nombre,$clientes[$i]->telefono);
                        $this->storeMsgChat($clientes[$i]->bot_id,$cliente_id,$message,0); //bot

                    }else{

                    }
                    
                }else{

                     $clientes[$i]->open_aiB = $respB;
                    //$clientes[$i]->open_aiB = $respB['open_ai'];

                }


            }else{

                $clientes[$i]->palabra_clave = null;
                $clientes[$i]->open_aiA = $respA;
                //$clientes[$i]->open_aiA = $respA['open_ai'];

            }
        }

        //return 1;

        return response()->json([
            //'clientes_ids'=>$clientes_ids,
            'clientes'=>$clientes,
            //'mensajes'=>$mensajes,
        ], 200);
    }

    private function getClienteId($bot_id, $nombre, $telefono)
    {
        $obj=BotCliente::
            where('bot_id',$bot_id)
            ->where('telefono',$telefono)
            ->first();

        if (!$obj)
        {

            //Proxima fecha de cobro
            $date = Carbon::now()->addMonth();
            $pay_next_day = $date->day;
            $pay_next_month = $date->month;
            $pay_next_year = $date->year;

            //Limite de prueba
            $date2 = Carbon::now()->addDays(3);
            $test_day = $date2->day;
            $test_month = $date2->month;
            $test_year = $date2->year;

            $nuevoObj=BotCliente::create([
                'bot_id'=>$bot_id,
                'nombre'=>$nombre,
                'telefono'=>$telefono,
                'status'=>1,
                'pay_next_day'=>$pay_next_day,
                'pay_next_month'=>$pay_next_month,
                'pay_next_year'=>$pay_next_year,
                'pago'=>0,
                'test_day'=>$test_day,
                'test_month'=>$test_month,
                'test_year'=>$test_year,
                
            ]);

            return $nuevoObj->id;
        }

        return $obj->id;
        
    }

    private function storeMsgChat($bot_id, $cliente_id, $text, $autor)
    {
        $nuevoObj=BotChat::create([
            'bot_id'=>$bot_id,
            'cliente_id'=>$cliente_id,
            'text'=>$text,
            'autor'=>$autor,
            'status'=>0, //sin procesar
            
        ]);

        return $nuevoObj->id;
        
    }
}
