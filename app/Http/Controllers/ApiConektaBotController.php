<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Crypt;

use Illuminate\Support\Facades\Auth;
use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\User;
use App\Models\Sistema;
use App\Models\Bot;
use App\Models\BotCliente;
use App\Models\BotChat;

use DateTime;
use DateInterval;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;

use App\Http\Traits\ApiPayPalTrait;

date_default_timezone_set('America/Mexico_City');

class ApiConektaBotController extends Controller
{
    use ApiPayPalTrait;

    //produccion
    public $key_servicios = "key_wBRS9rAKgFkVhlewKfWvUgS";
    //pruebas
    //public $key_servicios = "key_LtGFYFRqrKrYchrXBRHVdA";

    public function postCustomerConekta($name, $email, $phone, $reference, $random_key, $token_id, $key)
    {
        
        //Armando la peticion cURL
        $fields = array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            "metadata" => array(
                'reference' => $reference,
                'random_key' => $random_key,
            ),
            "payment_sources" => array(
                array(
                'type' => 'card', 
                'token_id' => $token_id,
                )
            )
        );
            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.conekta.io/customers");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/vnd.conekta-v2.0.0+json',
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic '.base64_encode($key.":")
        ));
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        //print($response); 
        //dd($response);
        /* $conekta = json_decode($response);

        if (property_exists($conekta, 'id')) {
            return $conekta->id;
        }else{
            return 0;
        } */

        return $response;

    }

    public function postOrderConekta(Request $request)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if(count($sistema)==0){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Sistema no configurado'], 409);
        }

        // La cadena cifrada que se envió desde Angular
        $cadenaEncriptada = $request->input('cliente_id');

        $claveAdicional = config('app.lada_c');

        try {
            $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);
        } catch (Exception $e) {
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        $cliente = BotCliente::find($cadenaDesencriptada);
        if (!$cliente)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el cliente con id '.$request->input('cliente_id')], 404);
        }

        //Buscar el total del sistema
        //para evitar fraude en los montos
        $total = $sistema[0]->costo_bot;

        //Desactivar el cliente
        $cliente->status = 0;
        $cliente->pago = 0; //No Pagado
        $cliente->tipo_pago = 1;
        $cliente->pay_amount = $total;
        $cliente->save();

        //Cargar las marcas sociales
        $user_social = User::with('marcas')
            ->where('bot_cliente_id', $cliente->id)
            ->first();

        //Desactivar las marcas del cliente
        if($user_social && count($user_social->marcas) > 0){
            for ($i=0; $i < count($user_social->marcas); $i++) { 
                $user_social->marcas[$i]->tipo_pago = 1;
                $user_social->marcas[$i]->status = 0;
                $user_social->marcas[$i]->pago = 0; //No pagada
                $user_social->marcas[$i]->save();
            }
        }

        //4% del total
        //$comision = ($total * 4)/100;
        $comision = 0;

        $amount = 100;
        $unit_price = (($total+$comision) - 1)*100;
        //$unit_price = $total*100 - 100;
        $quantity = "1";

        $name = $request->input('name');
        if ($request->input('name') == '' || $request->input('name') == null) {
            $name = 'User Internow ChatBot';
        }

        // Generar conekta_customer_id
        $conekta_customer_id = null;
        $conekta_obj = $this->postCustomerConekta(
            $name,
            $request->input('email'),
            $request->input('phone'),
            $request->input('reference'),
            $request->input('random_key'),
            $request->input('token_id'),
            $this->key_servicios
        );

        $conekta_obj = json_decode($conekta_obj);

        if (property_exists($conekta_obj, 'id')) {

            $conekta_customer_id = $conekta_obj->id;

            //guardar id para cobros automaticos
            $cliente->conekta_customer_id = $conekta_customer_id;
            $cliente->save();

        }else{
            return response()->json([
                'error'=>'Error al conectar con Conekta.',
                'conekta'=>$conekta_obj
            ], 500);
        }
       
        //Armando la peticion cURL
        $fields = array(
            "line_items" => array(
                array(
                'name' => $request->input('name_order'), 
                'unit_price' => $unit_price,
                'quantity' => $quantity,
                )
            ),
            "shipping_lines" => array(
                array(
                'amount' => $amount, 
                'carrier' => $request->input('carrier'),
                )
            ),
            "currency" => 'MXN',
            "customer_info" => array(
                'customer_id' => $conekta_customer_id,
            ),
            "shipping_contact" => array(
                'address' => array(
                    'street1' => $request->input('street1'), 
                    'postal_code' => $request->input('postal_code'),
                    'country' => 'MX',
                )
            ),
            "metadata" => array(
                'reference' => $request->input('reference'), 
                'more_info' => "Pago de Bot del cliente id".$cliente->id,
            ),
            "charges" => array(
                array(
                    'payment_method' => array(
                    'type' => 'default', 
                    )
                )
            ),
        );
            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.conekta.io/orders");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/vnd.conekta-v2.0.0+json',
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic '.base64_encode($this->key_servicios.":")
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        //print($response); 
        //dd($response);
        $conekta = json_decode($response);


        if (property_exists($conekta, 'id')) {

            $conekta_id = $conekta->id;

            //Actualizar el cliente con el id de conekta
            $cliente->conekta_id = $conekta_id;
            $cliente->save();

            //Verificar el status del pago
            $payment_status = null;
            if(property_exists($conekta, 'payment_status')){
                $payment_status = $conekta->payment_status;
            }

            //actualizar el status de la operacion
            $operacion = 0;
            if($payment_status != 'paid'){
                $operacion = 0;

                //Desactivar el cliente con pago declinado
                $cliente->tipo_pago = 1;
                $cliente->status = 0;
                $cliente->pago = 0; //No pagada
                $cliente->save();

                //Desactivar las marcas del cliente con pago declinado
                if($user_social && count($user_social->marcas) > 0){
                    for ($i=0; $i < count($user_social->marcas); $i++) { 
                        $user_social->marcas[$i]->tipo_pago = 1;
                        $user_social->marcas[$i]->status = 0;
                        $user_social->marcas[$i]->pago = 0; //No pagada
                        $user_social->marcas[$i]->conekta_id = $conekta_id;
                        $user_social->marcas[$i]->save();
                    }
                }

            }else if($payment_status == 'paid'){
                $operacion = 1;

                //Proxima fecha de cobro
                $date = Carbon::now()->addMonth();
                $pay_next_day = $date->day;
                $pay_next_month = $date->month;
                $pay_next_year = $date->year;

                $cliente->pay_next_day = $pay_next_day;
                $cliente->pay_next_month = $pay_next_month;
                $cliente->pay_next_year = $pay_next_year;

                //Si ya tenia subs la cancelo
                if($cliente->paypal_subscription_id){
                    $obj_cancel = $this->_cancelarSubscription($cliente->paypal_subscription_id);
                }

                //setear pago con tarjeta
                $cliente->paypal_subscription_id = null;
                $cliente->tipo_pago = 1;

                //Activar el cliente con pago exitoso
                $cliente->status = 1;
                $cliente->pago = 1; //Pagada
                $cliente->last_pay_date = date('Y-m-d H:i:s'); //Fecha de ultimo pago
                $cliente->count_querys = 0; //Reinicair contador
                $cliente->count_facturas = 0; //Reinicair contador
                $cliente->save();

                //Activar las marcas del cliente con pago exitoso
                if($user_social && count($user_social->marcas) > 0){
                    for ($i=0; $i < count($user_social->marcas); $i++) { 
                        $user_social->marcas[$i]->pay_next_day = $pay_next_day;
                        $user_social->marcas[$i]->pay_next_month = $pay_next_month;
                        $user_social->marcas[$i]->pay_next_year = $pay_next_year;
                        $user_social->marcas[$i]->tipo_pago = 1;
                        $user_social->marcas[$i]->status = 1;
                        $user_social->marcas[$i]->pago = 1; //Pagada
                        $user_social->marcas[$i]->conekta_id = $conekta_id;
                        $user_social->marcas[$i]->save();
                    }
                }

                //Enviar Email
                $details = [
                    'title' => 'Pago de Bot',
                    'body' => 'El cliente '.$cliente->nombre.' '.$cliente->telefono.' realizó el pago del Bot'
                ];

                \Mail::to('tonii.jaam@gmail.com')->send(new \App\Mail\PagoBotEmail($details));
                //\Mail::to('ramirez.fred016@gmail.com')->send(new \App\Mail\PagoBotEmail($details));

            }

            return response()->json([
                'conekta'=>$conekta,
                'cliente'=>$cliente
            ], 200);

        }else{

            return response()->json([
                'error'=>'Error al conectar con Conekta.',
                'conekta'=>$conekta
            ], 500);
        }

    }

    public function cobrosAutomaticos(Request $request)
    {
        set_time_limit(500);

        /*
        buscar las clientes activos
        que les corresponde la fecha de cobro
        */

        //fecha actual
        $date = Carbon::now();
        $pay_day = $date->day;
        $pay_month = $date->month;
        $pay_year = $date->year;

        $clientes = BotCliente::select('id','bot_id','nombre','telefono','status',
            'conekta_id','conekta_customer_id','paypal_id',
            'pay_next_day','pay_next_month','pay_next_year','pay_amount',
            'tipo_pago','pago')
            //->where('status', 1)
            ->where('pay_next_day', $pay_day)
            ->where('pay_next_month', $pay_month)
            ->where('pay_next_year', $pay_year)
            ->get();

        //Proxima fecha de cobro
        $date2 = Carbon::now()->addMonth();
        $pay_next_day = $date2->day;
        $pay_next_month = $date2->month;
        $pay_next_year = $date2->year;

        for ($i=0; $i < count($clientes); $i++) {

            $clientes[$i]->pay_next_day = $pay_next_day;
            $clientes[$i]->pay_next_month = $pay_next_month;
            $clientes[$i]->pay_next_year = $pay_next_year;
            $clientes[$i]->pago = 0; //No pagado
            $clientes[$i]->save();

            //Cargar las marcas sociales
            $user_social = User::with('marcas')
                ->where('bot_cliente_id', $clientes[$i]->id)
                ->first();

            if($user_social && count($user_social->marcas) > 0){
                for ($j=0; $j < count($user_social->marcas); $j++) { 
                    $user_social->marcas[$j]->pay_next_day = $pay_next_day;
                    $user_social->marcas[$j]->pay_next_month = $pay_next_month;
                    $user_social->marcas[$j]->pay_next_year = $pay_next_year;
                    $user_social->marcas[$j]->pago = 0; //No Pagada
                    $user_social->marcas[$j]->save();
                }
            }

            /*Cobro a los activos*/
            if ($clientes[$i]->status == 1) {
                if ($clientes[$i]->conekta_customer_id) {
                    $this->postOrderConektaTarjetaSave($clientes[$i]);
                }else if($clientes[$i]->paypal_subscription_id){
                    //el pago lo procesa paypal
                    //continue;
                    $continue = true;
                }else{
                    //no tiene ningun metodo de pago
                    $clientes[$i]->status = 0;
                    $clientes[$i]->save();

                    if($user_social && count($user_social->marcas) > 0){
                        for ($j=0; $j < count($user_social->marcas); $j++) { 
                            $user_social->marcas[$j]->status = 0; 
                            $user_social->marcas[$j]->save();
                        }
                    }
                }
            }
            
        }

        // return response()->json([
        //     'clientes'=>$clientes,
        // ], 200);

        return response()->json([
            'message'=>'Cobros realizados.',
        ], 200);
    }

    /*Pago con tarjeta guardada*/
    public function postOrderConektaTarjetaSave($cliente)
    {
        set_time_limit(500);
        
        $sistema = Sistema::all();
        if(count($sistema)==0){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Sistema no configurado'], 409);
        }
             
        //Buscar el total del sistema
        //para evitar fraude en los montos
        $total = $sistema[0]->costo_bot;

        //Desactivar el cliente
        $cliente->status = 0;
        $cliente->pago = 0; //No Pagada
        $cliente->tipo_pago = 1;
        $cliente->pay_amount = $total;
        $cliente->save();

        //Cargar las marcas sociales
        $user_social = User::with('marcas')
            ->where('bot_cliente_id', $cliente->id)
            ->first();

        //Desactivar las marcas del cliente
        if($user_social && count($user_social->marcas) > 0){
            for ($i=0; $i < count($user_social->marcas); $i++) { 
                $user_social->marcas[$i]->tipo_pago = 1;
                $user_social->marcas[$i]->status = 0;
                $user_social->marcas[$i]->pago = 0; //No pagada
                $user_social->marcas[$i]->save();
            }
        }

        //4% del total
        //$comision = ($total * 4)/100;
        $comision = 0;

        $amount = 100;
        $unit_price = (($total+$comision) - 1)*100;
        //$unit_price = $total*100 - 100;
        $quantity = "1";

        $name = 'User Intenow ChatBot';

        $reference=time(); 

        $conekta_customer_id = $cliente->conekta_customer_id;  
        
        //Armando la peticion cURL
        $fields = array(
            "line_items" => array(
                array(
                'name' => 'Pago cliente ChatBot', 
                'unit_price' => $unit_price,
                'quantity' => $quantity,
                )
            ),
            "shipping_lines" => array(
                array(
                'amount' => $amount, 
                'carrier' => "Intenow ChatBot",
                )
            ),
            "currency" => 'MXN',
            "customer_info" => array(
                'customer_id' => $conekta_customer_id,
            ),
            "shipping_contact" => array(
                'address' => array(
                    'street1' => 'Av. Central 111 2', 
                    'postal_code' => "43612",
                    'country' => 'MX',
                )
            ),
            "metadata" => array(
                'reference' => $reference, 
                'more_info' => "Pago de Bot del cliente id".$cliente->id,
            ),
            "charges" => array(
                array(
                    'payment_method' => array(
                    'type' => 'default', 
                    )
                )
            ),
        );
            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.conekta.io/orders");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/vnd.conekta-v2.0.0+json',
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic '.base64_encode($this->key_servicios.":")
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        //print($response); 
        //dd($response);
        $conekta = json_decode($response);

        if (property_exists($conekta, 'id')) {

            $conekta_id = $conekta->id;

            //Actualizar el cliente con el id de conekta
            $cliente->conekta_id = $conekta_id;
            $cliente->save();

            //Verificar el status del pago
            $payment_status = null;
            if(property_exists($conekta, 'payment_status')){
                $payment_status = $conekta->payment_status;
            }

            //actualizar el status de la operacion
            $operacion = 0;
            if($payment_status != 'paid'){
                $operacion = 0;

                //Desactivar el cliente con pago declinado
                $cliente->status = 0;
                $cliente->pago = 0; //No Pagada
                $cliente->tipo_pago = 1;
                $cliente->save();

                //Desactivar las marcas del cliente con pago declinado
                if($user_social && count($user_social->marcas) > 0){
                    for ($i=0; $i < count($user_social->marcas); $i++) { 
                        $user_social->marcas[$i]->tipo_pago = 1;
                        $user_social->marcas[$i]->status = 0;
                        $user_social->marcas[$i]->pago = 0; //No pagada
                        $user_social->marcas[$i]->conekta_id = $conekta_id;
                        $user_social->marcas[$i]->save();
                    }
                }

                //Enviar Email
                $details = [
                    'title' => 'Cobro automático de ChatBot',
                    'body' => 'Cobro automático declinado para el cliente '.$cliente->nombre.' '.$cliente->telefono
                ];

                \Mail::to('tonii.jaam@gmail.com')->send(new \App\Mail\PagoBotEmail($details));
                //\Mail::to('ramirez.fred016@gmail.com')->send(new \App\Mail\PagoBotEmail($details));

            }else if($payment_status == 'paid'){
                $operacion = 1;

                //Activar el cliente con pago exitoso
                $cliente->status = 1;
                $cliente->pago = 1; //Pagada
                $cliente->tipo_pago = 1;
                $cliente->last_pay_date = date('Y-m-d H:i:s'); //Fecha de ultimo pago
                $cliente->count_querys = 0; //Reinicair contador
                $cliente->count_facturas = 0; //Reinicair contador
                $cliente->save();

                //Activar las marcas del cliente con pago exitoso
                if($user_social && count($user_social->marcas) > 0){
                    for ($i=0; $i < count($user_social->marcas); $i++) { 
                        $user_social->marcas[$i]->tipo_pago = 1;
                        $user_social->marcas[$i]->status = 1;
                        $user_social->marcas[$i]->pago = 1; //Pagada
                        $user_social->marcas[$i]->conekta_id = $conekta_id;
                        $user_social->marcas[$i]->save();
                    }
                }

                //Enviar Email
                $details = [
                    'title' => 'Cobro automático de ChatBot',
                    'body' => 'Cobro automático exitoso para el cliente '.$cliente->nombre.' '.$cliente->telefono
                ];

                \Mail::to('tonii.jaam@gmail.com')->send(new \App\Mail\PagoBotEmail($details));
                //\Mail::to('ramirez.fred016@gmail.com')->send(new \App\Mail\PagoBotEmail($details));
            }

            //return response()->json(['conekta'=>$conekta], 200);
            return 1;

        }else{

            //Enviar Email
            $details = [
                'title' => 'Cobro automático de ChatBot',
                'body' => 'Cobro automático declinado para el cliente '.$cliente->nombre.' '.$cliente->telefono
            ];

            \Mail::to('tonii.jaam@gmail.com')->send(new \App\Mail\PagoBotEmail($details));
            //\Mail::to('ramirez.fred016@gmail.com')->send(new \App\Mail\PagoBotEmail($details));

            // return response()->json([
            //     'error'=>'Error al conectar con Conekta.',
            //     'conekta'=>$conekta
            // ], 500);
            return 0;
        }

    }

}
