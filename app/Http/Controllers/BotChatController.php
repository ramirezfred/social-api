<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use App\Models\Sistema;
use App\Models\Bot;
use App\Models\BotCliente;
use App\Models\BotChat;
use App\Models\BotConfig;

//use Hash;
use DB;
//use Validator;
use Exception;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

class BotChatController extends Controller
{
    public function show($cliente_id)
    {
        $cliente = BotCliente::
            select('id','bot_id','nombre','telefono')
            ->find($cliente_id);

        if (!$cliente)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        $bot = Bot::
            select('id','nombre','telefono')
            ->find($cliente->bot_id);

        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        $mensajes = BotChat::
            select('id','bot_id','cliente_id','text','autor','created_at')
            ->where('cliente_id', $cliente_id)
            ->get();

        for ($i=0; $i < count($mensajes); $i++) { 
            if($mensajes[$i]->autor == 0){
                $mensajes[$i]->autor_text = $bot->nombre;
            }
            if($mensajes[$i]->autor == 1){
                $mensajes[$i]->autor_text = $cliente->nombre;
            }
        }

        $chat = (object) [];
        $chat->bot = $bot;
        $chat->cliente = $cliente;
        $chat->mensajes = $mensajes;

        return response()->json([
            'chat'=>$chat,
        ], 200);
    }

    public function autoborrarChatsDown()
    {
        $date = Carbon::now();
        $test_day = $date->day;
        $test_month = $date->month;
        $test_year = $date->year;

        $objs = BotCliente::
            select('id')
            ->with(['mensajes' => function ($query) {
                $query->select('id','cliente_id','created_at')
                    ->orderBy('id', 'desc')
                    ->take(1);
            }])
            ->get();

        for ($i=0; $i < count($objs); $i++) { 

            if(count($objs[$i]->mensajes) == 1){
                $fecha2 = Carbon::create($objs[$i]->mensajes[0]->created_at); // Segunda fecha

                $diferenciaEnDias = $date->diffInDays($fecha2);

                /*Borra los chats que ya tienen 4 dias
                los extremos no los cuenta diffInDays*/
                if($diferenciaEnDias >= 2){

                    DB::table('bot_chats')
                        ->where('cliente_id', $objs[$i]->id)
                        ->delete();

                }
            }
            
        }

        return response()->json(['clientes'=>$objs], 200);
    }

    public function autoborrarChats()
    {
        $date = Carbon::now();
        $dateRestada = $date->subDays(3);

        DB::table('bot_chats')
            ->where('created_at', '<=', $dateRestada)
            ->delete();

        return response()->json([
            'date'=>$date,
            'dateRestada'=>$dateRestada,
        ], 200);
    }

    public function resetCountQuerys()
    {
        $date = Carbon::now();
        $test_day = $date->day;
        $test_month = $date->month;
        $test_year = $date->year;

        if($test_day == 1){
            DB::table('bot_clientes')
                ->update([
                    'count_querys' => 0,
                ]);
        }

        return response()->json([
            'date'=>$date,
            'test_day'=>$test_day,
        ], 200);
    }
}
