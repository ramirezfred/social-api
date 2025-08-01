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

use App\Models\BotFlow;
use App\Models\BotFlowStage;
use App\Models\BotStageValidation;

//use Hash;
use DB;
//use Validator;
use Exception;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

class BotFlowController extends Controller
{
    public function indexFlows($bot_id)
    {

        $objs = BotFlow::
            where('bot_id',$bot_id)
            ->get();

        return response()->json(['flujos'=>$objs], 200);
    }

    public function storeFlow(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'bot_id'=>'required|integer',
            'nombre'=>'required|string',

        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $bot = Bot::find($request->input('bot_id'));
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el Bot con id '.$request->input('bot_id')], 404);
        }

        $nombre = strtoupper($request->input('nombre'));

        $nombre_aux = BotFlow::
            select('id','nombre')
            ->where('nombre', $nombre)
            ->where('bot_id', $request->input('bot_id'))
            ->get();
        if (count($nombre_aux)>0) {
            return response()->json(['error'=>'Ya existe un flujo con ese nombre.'], 409);
        }

        if($nuevoObj=BotFlow::create([
            'bot_id'=>$request->input('bot_id'),
            'nombre'=>$nombre,
            'status'=>1,
            
        ])){

            return response()->json([
                'message'=>'Flujo agregado con éxito.',
                'flujo'=>$nuevoObj,
            ], 200);

        }else{
            return response()->json(['error'=>'Error al crear el flujo.'], 500);
        }

    }

    public function indexFlowStages($flow_id)
    {

        $objs = BotFlowStage::
            where('flow_id',$flow_id)
            ->orderBy('item','asc')
            ->get();

        return response()->json(['estados'=>$objs], 200);
    }

    public function storeFlowStage(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'flow_id'=>'required|integer',
            'tipo'=>'required|integer',
            'prompt'=>'string',
            'text'=>'string',

        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $flow = BotFlow::find($request->input('flow_id'));
        if (!$flow)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el Flujo con id '.$request->input('flow_id')], 404);
        }

        if($request->input('tipo') != 1 && $request->input('tipo') != 2){
            return response()->json(['error'=>'Tipo debe ser 1 o 2'], 409);
        }

        if($request->input('tipo') == 1){

            if (strlen($request->input('prompt')) == 0)
            {
                // Devolvemos error codigo http 409
                return response()->json([
                    'error'=>'Prompt inválido.',
                    'count_prompt'=>strlen($request->input('prompt'))
                ], 409);
            }

            if (strlen($request->input('prompt')) > 4800)
            {
                // Devolvemos error codigo http 409
                return response()->json([
                    'error'=>'El prompt no puede contener más de 4800 caracteres',
                    'count_prompt'=>strlen($request->input('prompt'))
                ], 409);
            }

            // //verificar la precencia de {{mensaje}}
            // $mensaje = strpos($request->input('prompt'), '{{mensaje}}');
            // if ($mensaje === false) {
            //     return response()->json(['error'=>'El prompt debe contener la subcadena {{mensaje}}'], 409);
            // }
        }

        if($request->input('tipo') == 2){

            if (strlen($request->input('text')) == 0)
            {
                // Devolvemos error codigo http 409
                return response()->json([
                    'error'=>'Texto inválido.',
                    'count_text'=>strlen($request->input('text'))
                ], 409);
            }

            if (strlen($request->input('text')) > 4000)
            {
                // Devolvemos error codigo http 409
                return response()->json([
                    'error'=>'El texto no puede contener más de 4000 caracteres',
                    'count_text'=>strlen($request->input('text'))
                ], 409);
            }
        }

        $count_item = BotFlowStage::
            where('flow_id', $request->input('flow_id'))
            ->count();

        if($nuevoObj=BotFlowStage::create([
            'flow_id'=>$request->input('flow_id'),
            'item'=>$count_item + 1,
            'tipo'=>$request->input('tipo'),
            'prompt'=>$request->input('prompt'),
            'text'=>$request->input('text'),
            
        ])){

            return response()->json([
                'message'=>'Estado agregado con éxito.',
                'estado'=>$nuevoObj,
            ], 200);

        }else{
            return response()->json(['error'=>'Error al crear el estado.'], 500);
        }

    }

    public function indexStageValidations($stage_id)
    {

        $objs = BotStageValidation::
            where('stage_id',$stage_id)
            ->get();

        return response()->json(['validaciones'=>$objs], 200);
    }

    public function storeStageValidation(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'stage_id'=>'required|integer',
            'tipo'=>'required|integer',
            'prompt'=>'string',
            'funcion'=>'string',
            'tipo_resp'=>'required|integer',
            'prompt_resp'=>'string',
            'text_resp'=>'string',

        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $stage = BotStageValidation::find($request->input('stage_id'));
        if (!$stage)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el Estado con id '.$request->input('stage_id')], 404);
        }

        if($request->input('tipo') != 1 && $request->input('tipo') != 2){
            return response()->json(['error'=>'Tipo debe ser 1 o 2'], 409);
        }

        if($request->input('tipo') == 1){

            if (strlen($request->input('prompt')) == 0)
            {
                // Devolvemos error codigo http 409
                return response()->json([
                    'error'=>'Prompt inválido.',
                    'count_prompt'=>strlen($request->input('prompt'))
                ], 409);
            }

            if (strlen($request->input('prompt')) > 4800)
            {
                // Devolvemos error codigo http 409
                return response()->json([
                    'error'=>'El prompt no puede contener más de 4800 caracteres',
                    'count_prompt'=>strlen($request->input('prompt'))
                ], 409);
            }

            //verificar la precencia de {{mensaje}}
            $mensaje = strpos($request->input('prompt'), '{{mensaje}}');
            if ($mensaje === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{mensaje}}'], 409);
            }
        }

        if($request->input('tipo') == 2){

            if (strlen($request->input('funcion')) == 0)
            {
                // Devolvemos error codigo http 409
                return response()->json([
                    'error'=>'Nombre de función inválido.',
                    'count_funcion'=>strlen($request->input('funcion'))
                ], 409);
            }

            if (strlen($request->input('funcion')) > 50)
            {
                // Devolvemos error codigo http 409
                return response()->json([
                    'error'=>'El texto no puede contener más de 50 caracteres',
                    'count_funcion'=>strlen($request->input('funcion'))
                ], 409);
            }
        }

        if($request->input('tipo_resp') != 1 && $request->input('tipo_resp') != 2){
            return response()->json(['error'=>'Tipo de respuesta debe ser 1 o 2'], 409);
        }

        if($request->input('tipo_resp') == 1){

            if (strlen($request->input('prompt_resp')) == 0)
            {
                // Devolvemos error codigo http 409
                return response()->json([
                    'error'=>'Prompt de respuesta inválido.',
                    'count_prompt'=>strlen($request->input('prompt_resp'))
                ], 409);
            }

            if (strlen($request->input('prompt_resp')) > 4800)
            {
                // Devolvemos error codigo http 409
                return response()->json([
                    'error'=>'El prompt de respuesta no puede contener más de 4800 caracteres',
                    'count_prompt'=>strlen($request->input('prompt_resp'))
                ], 409);
            }

        }

        if($request->input('tipo_resp') == 2){

            if (strlen($request->input('text_resp')) == 0)
            {
                // Devolvemos error codigo http 409
                return response()->json([
                    'error'=>'Texto de respuesta inválido.',
                    'count_text'=>strlen($request->input('text_resp'))
                ], 409);
            }

            if (strlen($request->input('text_resp')) > 4000)
            {
                // Devolvemos error codigo http 409
                return response()->json([
                    'error'=>'El texto de respuesta no puede contener más de 4000 caracteres',
                    'count_text'=>strlen($request->input('text_resp'))
                ], 409);
            }
        }

        if($nuevoObj=BotStageValidation::create([
            'stage_id'=>$request->input('stage_id'),
            'tipo'=>$request->input('tipo'),
            'prompt'=>$request->input('prompt'),
            'funcion'=>$request->input('funcion'),
            'tipo_resp'=>$request->input('tipo_resp'),
            'prompt_resp'=>$request->input('prompt_resp'),
            'text_resp'=>$request->input('text_resp'),
            
        ])){

            return response()->json([
                'message'=>'Validación agregada con éxito.',
                'validacion'=>$nuevoObj,
            ], 200);

        }else{
            return response()->json(['error'=>'Error al crear la validación.'], 500);
        }

    }
}
