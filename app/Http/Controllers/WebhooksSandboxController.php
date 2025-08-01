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
use App\Models\Bot;

//use Hash;
use DB;
//use Validator;
use Exception;


use Carbon\Carbon;

use App\Http\Traits\ApiMetaSandboxTrait;
use App\Http\Traits\ApiWhatsAppTrait;
use App\Http\Traits\ApiTextCortexTrait;

class WebhooksSandboxController extends Controller
{
    use ApiMetaSandboxTrait;
    use ApiWhatsAppTrait;
    use ApiTextCortexTrait;

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

    public function metaHandleA(Request $request)
    {
        $input = $request->all();
        // Aquí puedes realizar cualquier acción necesaria con los datos recibidos del Webhook

        // Verificar el tipo de evento
        if ($input['object'] === 'page' && isset($input['entry'][0]['changes'])) {
            foreach ($input['entry'][0]['changes'] as $change) {
                if ($change['field'] === 'feed') {
                    // Procesar el evento de feed
                    $postId = $change['value']['post_id'];
                    //$pageId = $change['value']['page_id'];

                    // Ejecutar acciones específicas en base al evento de feed
                    // Por ejemplo, obtener el contenido del post
                    $post = $this->getFacebookPost($postId);

                    // Por ejemplo, guardar los datos recibidos en un archivo de registro
                    file_put_contents('webhook_fb_log200.txt', print_r($input, true), FILE_APPEND);

                    // Realizar cualquier otra acción necesaria con el post, como almacenarlo en la base de datos o enviar una notificación, etc.

                    // Regresar una respuesta exitosa a Facebook
                    return response('OK', 200);
                }
            }
        }

        // Verificar el tipo de evento
        if ($input['object'] === 'instagram' && isset($input['entry'][0]['changes'])) {

            //$pageId = $input['entry'][0]['id'];
            $pageId = '17841444793341089';

            foreach ($input['entry'][0]['changes'] as $change) {
                if ($change['field'] === 'comments') {

                    // Procesar el evento de comment
                    //$commentId = $change['value']['id'];
                    $commentId = '17919161861725827';

                    $text = $change['value']['text'];

                    $redTipo = 2;
                    
                    $respuesta = $this->respuestaAuto($redTipo,$pageId,$commentId,$text);

                    // Por ejemplo, guardar los datos recibidos en un archivo de registro
                    file_put_contents('webhook_ig_log200.txt', print_r($input, true), FILE_APPEND);

                    // Regresar una respuesta exitosa a Facebook
                    return response('OK', 200);
                }
            }
        }

        // Si no se procesa un evento de feed, puedes realizar otras acciones o retornar una respuesta diferente si es necesario

        // Por ejemplo, guardar los datos recibidos en un archivo de registro
        file_put_contents('webhook_log500.txt', print_r($input, true), FILE_APPEND);
        //file_put_contents('webhook_log.txt', print_r($input['field'], true), FILE_APPEND);

        // Regresar una respuesta exitosa a Facebook
        return response('OK', 200);
    }

    private function getFacebookPost($postId)
    {
        // Aquí puedes implementar la lógica para obtener el contenido del post utilizando la API de Facebook

        // Por ejemplo, utilizando el SDK de Facebook o mediante una solicitud HTTP a la API de Facebook

        // Devolver el contenido del post
        return 'Contenido del post';
    }


    private function getInstagramComment($commentId)
    {
        return 'Contenido del comment';
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

        $message = 'Respuesta Sanbox Webhook';

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($obj->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        $resp = $this->_postReplies($commentId,$cadenaDesencriptada,$message);
        if ($resp['status'] == 200) {
            return response()->json([
                'meta'=>$resp['meta']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'meta'=>$resp['meta']
            ], $resp['status']); 
        }
    }

    public function metaHandle(Request $request)
    {

        set_time_limit(500);
        
        $input = $request->all();
        // Aquí puedes realizar cualquier acción necesaria con los datos recibidos del Webhook

        // Verificar el tipo de evento
        if ($input['object'] === 'whatsapp_business_account' && isset($input['entry'][0]['changes'])) {

            $whatsapp_id = $input['entry'][0]['id'];

            foreach ($input['entry'][0]['changes'] as $change) {
                if ($change['field'] === 'messages') {


                        if(
                            isset($change['value']['contacts']) &&
                            isset($change['value']['messages']) 
                        ){

                            // Procesar el evento de messages

                            $name = $change['value']['contacts'][0]['profile']['name'];
                            $wa_id = $change['value']['contacts'][0]['wa_id'];

                            $from = $change['value']['messages'][0]['from'];
                            $type = $change['value']['messages'][0]['type'];

                            if ($type != 'text'){
                                file_put_contents('webhook_ws_log200.txt', print_r($input, true), FILE_APPEND);

                                return response('OK', 200);
                            }

                            $body = $change['value']['messages'][0]['text']['body'];
                            

                            // $log = [];
                            // $resul = (object) [
                            //     'name' => $name,
                            //     'wa_id' => $wa_id,
                            // ];
                            // array_push($log,$resul);

                            $respuesta = $this->respuestaAutoWs($whatsapp_id,$name,$from,$body,$type);

                            // Por ejemplo, guardar los datos recibidos en un archivo de registro
                            //file_put_contents('webhook_ws_log200.txt', print_r($log, true), FILE_APPEND);

                            file_put_contents('webhook_ws_log200.txt', print_r($input, true), FILE_APPEND);

                            // Realizar cualquier otra acción necesaria con el post, como almacenarlo en la base de datos o enviar una notificación, etc.

                            // Regresar una respuesta exitosa a Facebook
                            return response('OK', 200);

                        }

                    
 
                }
            }

            // Regresar una respuesta exitosa a Facebook
            return response('OK', 200);
        }


        // Si no se procesa un evento de feed, puedes realizar otras acciones o retornar una respuesta diferente si es necesario

        // Por ejemplo, guardar los datos recibidos en un archivo de registro
        file_put_contents('webhook_ws_log500.txt', print_r($input, true), FILE_APPEND);
        //file_put_contents('webhook_log.txt', print_r($input['field'], true), FILE_APPEND);

        // Regresar una respuesta exitosa a Facebook
        return response('OK', 200);
    }

    private function respuestaAutoWs($whatsapp_id,$name,$from,$body,$type)
    {
        $obj=Bot::
            where('whatsapp_id',$whatsapp_id)
            ->first();

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Bot no encontrado.'], 404);
        }

        $log = [];
        $resul = (object) [
            'whatsapp_id' => $whatsapp_id,
            'name' => $name,
            'from' => $from,
            'body' => $body,
            'type' => $type,
        ];
        array_push($log,$resul);

        $message = 'Respuesta al Webhook de WhatsApp';
        /*$respA = $this->_completions($obj->id,$body);
        if ($respA['status'] == 200) {

            array_push($log,$respA['textcortex']);
            $message = $respA['text'];
            
        }else{

            //errores de chatGPT
            array_push($log,$respA);
            file_put_contents('webhook_log_ws_message.txt', print_r($log, true), FILE_APPEND);

           return response()->json([
                //'error'=>$respA['error'],
                'textcortex'=>$respA['textcortex']
            ], $respA['status']); 
        }*/

        $resp = $this->_messageText($obj->id,$from,$message);

        array_push($log,$resp);
        file_put_contents('webhook_log_ws_message.txt', print_r($log, true), FILE_APPEND);

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


    


}
