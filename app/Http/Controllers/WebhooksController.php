<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Crypt;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\SocialImage;
use App\Models\Sistema;

//use Hash;
use DB;
//use Validator;
use Exception;

use Carbon\Carbon;

use App\Http\Traits\ApiMetaTrait;
use App\Http\Traits\ApiOpenAiTrait;

class WebhooksController extends Controller
{
    use ApiMetaTrait;
    use ApiOpenAiTrait;

    //Facebook
    public function meta(Request $request)
    {
        //https://apisocial.internow.com.mx/api/webhooks/facebook

        $challenge = $request->input('hub_challenge');
        $verify_token = $request->input('hub_verify_token');

        if ($verify_token === 'ladawebhookmeta') {
            return $challenge;
            //return response($challenge, 200);
        }

        $input = $request->all();
        // Aquí puedes realizar cualquier acción necesaria con los datos recibidos del Webhook

        // Por ejemplo, guardar los datos recibidos en un archivo de registro
        file_put_contents('webhook_log.txt', print_r($input, true), FILE_APPEND);
    }

    public function handle(Request $request)
    {
        $input = $request->all();
        // Aquí puedes realizar cualquier acción necesaria con los datos recibidos del Webhook

        // Verificar el tipo de evento
        if ($input['object'] === 'page' && isset($input['entry'][0]['messaging'])) {
            foreach ($input['entry'][0]['messaging'] as $message) {
                if (isset($message['post'])) {
                    // Procesar el comentario en la publicación
                    $postId = $message['post']['id'];
                    $commentId = $message['post']['comments']['comment_id'];
                    $senderId = $message['sender']['id'];

                    // Ejecutar acciones específicas en base al comentario
                    // Por ejemplo, obtener el contenido del comentario
                    $comment = $this->getFacebookComment($commentId);

                    // Realizar cualquier otra acción necesaria con el comentario, como almacenarlo en la base de datos o enviar una notificación, etc.

                    // Regresar una respuesta exitosa a Facebook
                    return response('OK', 200);
                }
            }
        }

        // Si no se procesa un evento de comentario, puedes realizar otras acciones o retornar una respuesta diferente si es necesario

        // Por ejemplo, guardar los datos recibidos en un archivo de registro
        file_put_contents('webhook_log.txt', print_r($input, true), FILE_APPEND);

        // Regresar una respuesta exitosa a Facebook
        return response('OK', 200);
    }

    public function metaHandle(Request $request)
    {

        set_time_limit(500);
        
        $input = $request->all();
        // Aquí puedes realizar cualquier acción necesaria con los datos recibidos del Webhook

        // Verificar el tipo de evento
        if ($input['object'] === 'page' && isset($input['entry'][0]['changes'])) {

            $pageId = $input['entry'][0]['id'];
            //$pageId = '';

            foreach ($input['entry'][0]['changes'] as $change) {
                if ($change['field'] === 'feed') {

                    if($change['value']['from']['id'] !== $pageId){

                        if(
                            isset($change['value']['verb']) && $change['value']['verb'] === 'add' &&
                            isset($change['value']['item']) && $change['value']['item'] === 'comment' &&
                            isset($change['value']['message']) &&
                            isset($change['value']['comment_id'])
                        ){

                            // Procesar el evento de feed

                            $commentId = $change['value']['comment_id'];

                            $text = $change['value']['message'];

                            $redTipo = 1;
                            
                            $respuesta = $this->respuestaAuto($redTipo,$pageId,$commentId,$text);

                            // Por ejemplo, guardar los datos recibidos en un archivo de registro
                            file_put_contents('webhook_fb_log200.txt', print_r($input, true), FILE_APPEND);

                            // Realizar cualquier otra acción necesaria con el post, como almacenarlo en la base de datos o enviar una notificación, etc.

                            // Regresar una respuesta exitosa a Facebook
                            return response('OK', 200);

                        }

                    }
 
                }
            }

            // Regresar una respuesta exitosa a Facebook
            return response('OK', 200);
        }

        // Verificar el tipo de evento
        if ($input['object'] === 'instagram' && isset($input['entry'][0]['changes'])) {

            $pageId = $input['entry'][0]['id'];
            //$pageId = '17841444793341089';

            foreach ($input['entry'][0]['changes'] as $change) {
                if ($change['field'] === 'comments') {

                    if($change['value']['from']['id'] !== $pageId){

                        // if(
                        //     isset($change['value']['verb']) && $change['value']['verb'] === 'add' &&
                        //     isset($change['value']['item']) && $change['value']['item'] === 'comment' &&
                        //     isset($change['value']['message']) &&
                        //     isset($change['value']['comment_id'])
                        // ){

                            // Procesar el evento de comment
                            $commentId = $change['value']['id'];
                            //$commentId = '17842801580998171';
                            //$commentId = $change['value']['comment_id'];

                            $text = $change['value']['text'];
                            //$text = $change['value']['message'];

                            $redTipo = 2;
                            
                            $respuesta = $this->respuestaAuto($redTipo,$pageId,$commentId,$text);

                            // Guardar los datos recibidos en un archivo de registro
                            file_put_contents('webhook_ig_log200.txt', print_r($input, true), FILE_APPEND);

                            // Realizar cualquier otra acción necesaria con el post, como almacenarlo en la base de datos o enviar una notificación, etc.

                            // Regresar una respuesta exitosa a Facebook
                            return response('OK', 200);

                        //}

                    }
                }
            }

            // Regresar una respuesta exitosa a Facebook
            return response('OK', 200);
        }

        // Si no se procesa un evento de feed, puedes realizar otras acciones o retornar una respuesta diferente si es necesario

        // Por ejemplo, guardar los datos recibidos en un archivo de registro
        file_put_contents('webhook_log500.txt', print_r($input, true), FILE_APPEND);
        //file_put_contents('webhook_log.txt', print_r($input['field'], true), FILE_APPEND);

        // Regresar una respuesta exitosa a Facebook
        return response('OK', 200);
    }


    private function respuestaAuto($redTipo,$pageId,$commentId,$text)
    {
        $obj=SocialNetwork::
            where('tipo',$redTipo)
            ->where('page_id',$pageId)
            ->first();

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Red no encontrada.'], 404);
        }

        $marca = SocialBrand::select('id','nombre',
            'comment_status','comment_auto','comment_footer')
            ->find($obj->brand_id);
        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la marca con id '.$obj->brand_id], 404);
        }

        $log = [];
        $resul = (object) [
            'id' => $marca->id,
            'nombre' => $marca->nombre,
            'comment_status' => $marca->comment_status,
            //'comment_auto' => $marca->comment_auto,
            //'comment_footer' => $marca->comment_footer,
            'text' => $text,
        ];
        array_push($log,$resul);

        $message = '';
        if($marca->comment_status === 1){

            $respA = $this->_davinciRespuesta($obj->brand_id,$text);
            if ($respA['status'] == 200) {
                $message = $respA['respuesta'];
            }else{

                //errores de chatGPT
                array_push($log,$respA);
                file_put_contents('webhook_log_chatGPT.txt', print_r($log, true), FILE_APPEND);

               return response()->json([
                    'error'=>$respA['error'],
                    'open_ai'=>$respA['open_ai']
                ], $respA['status']); 
            }

        }else if($marca->comment_status === 2){
            if ($marca->comment_auto === null || $marca->comment_auto === '')
            {
                return response('OK', 200);
            }else{
                $message = $marca->comment_auto; 
            }
        }else{
            //No responder
            return response('OK', 200);
        }

        if($marca->comment_footer != null && $marca->comment_footer != ''){

            $message_compuesto = '{{message}}
.
.
.
{{footer}}';

        $message = str_replace("{{message}}", $message, $message_compuesto);
        $message = str_replace("{{footer}}", $marca->comment_footer, $message);

        }

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($obj->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        if($redTipo == 1){
            $resp = $this->_postComments($commentId,$cadenaDesencriptada,$message);

            //log de FB
            array_push($log,$resp);
            file_put_contents('webhook_log_fb_comment.txt', print_r($log, true), FILE_APPEND);
        }
        if($redTipo == 2){
            $resp = $this->_postReplies($commentId,$cadenaDesencriptada,$message);

            //log de IG
            array_push($log,$resp);
            file_put_contents('webhook_log_ig_comment.txt', print_r($log, true), FILE_APPEND);
        }
        
        // if ($resp['status'] == 200) {
        //     return response()->json([
        //         'message'=>$message,
        //         'meta'=>$resp['meta'],
        //         'open_ai'=>$respA['open_ai'],
                
        //     ], $resp['status']);
        // }else{
        //    return response()->json([
        //         'message'=>$message,
        //         'error'=>$resp['error'],
        //         'meta'=>$resp['meta'],
        //         'open_ai'=>$respA['open_ai'],
                
        //     ], $resp['status']); 
        // }

        return response('OK', 200);
    }

    


}
