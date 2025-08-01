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
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\Sistema;

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

class ApiConektaController extends Controller
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
        $cadenaEncriptada = $request->input('brand_id');

        $claveAdicional = config('app.lada_a');

        try {
            $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);
        } catch (Exception $e) {
            return response()->json(['error'=>'Marca no encontrada'], 404);
        }

        $marca = SocialBrand::with('user')->find($cadenaDesencriptada);
        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la marca con id '.$request->input('brand_id')], 404);
        }

        //Buscar el total del sistema
        //para evitar fraude en los montos
        $total = $sistema[0]->costo_marca;

        //Desactivar la marca
        $marca->status = 0;
        $marca->pago = 0; //No Pagada
        $marca->tipo_pago = 1;
        $marca->pay_amount = $total;
        $marca->save();

        //4% del total
        //$comision = ($total * 4)/100;
        $comision = 0;

        $amount = 100;
        $unit_price = (($total+$comision) - 1)*100;
        //$unit_price = $total*100 - 100;
        $quantity = "1";

        $name = $request->input('name');
        if ($request->input('name') == '' || $request->input('name') == null) {
            $name = 'User Internow Social';
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
            $marca->conekta_customer_id = $conekta_customer_id;
            $marca->save();

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
                'more_info' => 'Pago de Marca',
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

            //Actualizar la marca con el id de conekta
            $marca->conekta_id = $conekta_id;
            $marca->save();

            //Verificar el status del pago
            $payment_status = null;
            if(property_exists($conekta, 'payment_status')){
                $payment_status = $conekta->payment_status;
            }

            //actualizar el status de la operacion
            $operacion = 0;
            if($payment_status != 'paid'){
                $operacion = 0;

                //Desactivar la marca con pago declinado
                $marca->tipo_pago = 1;
                $marca->status = 0;
                $marca->pago = 0; //No pagada
                $marca->save();

            }else if($payment_status == 'paid'){
                $operacion = 1;

                //Proxima fecha de cobro
                $date = Carbon::now()->addMonth();
                $pay_next_day = $date->day;
                $pay_next_month = $date->month;
                $pay_next_year = $date->year;

                $marca->pay_next_day = $pay_next_day;
                $marca->pay_next_month = $pay_next_month;
                $marca->pay_next_year = $pay_next_year;

                //Si ya tenia subs la cancelo
                if($marca->paypal_subscription_id){
                    $obj_cancel = $this->_cancelarSubscription($marca->paypal_subscription_id);
                }

                //setear pago con tarjeta
                $marca->paypal_subscription_id = null;
                $marca->tipo_pago = 1;

                //Activar la marca con pago exitoso
                $marca->status = 1;
                $marca->pago = 1; //Pagada
                $marca->save();

                //Enviar Email
                $details = [
                    'title' => 'Pago de Marca',
                    'body' => 'El usuario '.$marca->user->email.' realizó el pago de la marca '.$marca->nombre
                ];

                \Mail::to('tonii.jaam@gmail.com')->send(new \App\Mail\PagoMarcaEmail($details));
                //\Mail::to('ramirez.fred016@gmail.com')->send(new \App\Mail\PagoMarcaEmail($details));

            }

            return response()->json([
                'conekta'=>$conekta,
                'marca'=>$marca
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

        return 1;
        
        set_time_limit(500);

        /*
        buscar las marcas activas
        de clientes activos
        que les corresponde la fecha de cobro
        */

        //fecha actual
        $date = Carbon::now();
        $pay_day = $date->day;
        $pay_month = $date->month;
        $pay_year = $date->year;

        $marcas = SocialBrand::select('id','user_id','nombre','status',
            'conekta_id','conekta_customer_id','paypal_id',
            'pay_next_day','pay_next_month','pay_next_year','pay_amount',
            'tipo_pago','pago')
            //->where('status', 1)
            ->where('pay_next_day', $pay_day)
            ->where('pay_next_month', $pay_month)
            ->where('pay_next_year', $pay_year)
            ->whereHas('user', function ($query) {
                $query->where('tipo', 2)
                    ->where('status', 1);
            })
            ->get();

        //Proxima fecha de cobro
        $date2 = Carbon::now()->addMonth();
        $pay_next_day = $date2->day;
        $pay_next_month = $date2->month;
        $pay_next_year = $date2->year;

        for ($i=0; $i < count($marcas); $i++) {

            $marcas[$i]->pay_next_day = $pay_next_day;
            $marcas[$i]->pay_next_month = $pay_next_month;
            $marcas[$i]->pay_next_year = $pay_next_year;
            $marcas[$i]->pago = 0; //No pagada
            $marcas[$i]->save();

            /*Cobro a las activas*/
            if ($marcas[$i]->status == 1) {
                if ($marcas[$i]->conekta_customer_id) {
                    $this->postOrderConektaTarjetaSave($marcas[$i]);
                }else if($marcas[$i]->paypal_subscription_id){
                    //el pago lo procesa paypal
                    //continue;
                    $aux = 'El pago lo procesa paypal';
                }else{
                    //no tiene ningun metodo de pago
                    $marcas[$i]->status = 0;
                    $marcas[$i]->save();
                }
            }
            
        }

        // return response()->json([
        //     'marcas'=>$marcas,
        // ], 200);

        return response()->json([
            'message'=>'Cobros realizados.',
        ], 200);
    }

    /*Pago con tarjeta guardada*/
    public function postOrderConektaTarjetaSave($marca)
    {
        set_time_limit(500);
        
        $sistema = Sistema::all();
        if(count($sistema)==0){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Sistema no configurado'], 409);
        }
             
        //Buscar el total del sistema
        //para evitar fraude en los montos
        $total = $sistema[0]->costo_marca;

        //Desactivar la marca
        $marca->status = 0;
        $marca->pago = 0; //No Pagada
        $marca->tipo_pago = 1;
        $marca->pay_amount = $total;
        $marca->save();

        //4% del total
        //$comision = ($total * 4)/100;
        $comision = 0;

        $amount = 100;
        $unit_price = (($total+$comision) - 1)*100;
        //$unit_price = $total*100 - 100;
        $quantity = "1";

        $name = 'User Intenow Social';

        $reference=time(); 

        $conekta_customer_id = $marca->conekta_customer_id;  
        
        //Armando la peticion cURL
        $fields = array(
            "line_items" => array(
                array(
                'name' => 'Pago marca social', 
                'unit_price' => $unit_price,
                'quantity' => $quantity,
                )
            ),
            "shipping_lines" => array(
                array(
                'amount' => $amount, 
                'carrier' => "Intenow Social",
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
                'more_info' => "Pago de marca id".$marca->id,
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

            //Actualizar la marca con el id de conekta
            $marca->conekta_id = $conekta_id;
            $marca->save();

            //Verificar el status del pago
            $payment_status = null;
            if(property_exists($conekta, 'payment_status')){
                $payment_status = $conekta->payment_status;
            }

            //actualizar el status de la operacion
            $operacion = 0;
            if($payment_status != 'paid'){
                $operacion = 0;

                //Desactivar la marca con pago declinado
                $marca->status = 0;
                $marca->pago = 0; //No Pagada
                $marca->tipo_pago = 1;
                $marca->save();

                //Enviar Email
                $details = [
                    'title' => 'Cobro automático de Marca',
                    'body' => 'Cobro automático declinado para la marca '.$marca->nombre
                ];

                \Mail::to('tonii.jaam@gmail.com')->send(new \App\Mail\PagoMarcaEmail($details));

            }else if($payment_status == 'paid'){
                $operacion = 1;

                //Activar la marca con pago exitoso
                $marca->status = 1;
                $marca->pago = 1; //Pagada
                $marca->tipo_pago = 1;
                $marca->save();

                //Enviar Email
                $details = [
                    'title' => 'Cobro automático de Marca',
                    'body' => 'Cobro automático exitoso para la marca '.$marca->nombre
                ];

                \Mail::to('tonii.jaam@gmail.com')->send(new \App\Mail\PagoMarcaEmail($details));
            }

            //return response()->json(['conekta'=>$conekta], 200);
            return 1;

        }else{

            //Enviar Email
            $details = [
                'title' => 'Cobro automático de Marca',
                'body' => 'Cobro automático declinado para la marca '.$marca->nombre
            ];

            \Mail::to('tonii.jaam@gmail.com')->send(new \App\Mail\PagoMarcaEmail($details));

            // return response()->json([
            //     'error'=>'Error al conectar con Conekta.',
            //     'conekta'=>$conekta
            // ], 500);
            return 0;
        }

    }

    public function cobroAutomaticoMarca($marca_id)
    {
        set_time_limit(500);

        /*
        buscar las marcas activas
        de clientes activos
        que les corresponde la fecha de cobro
        */

        //fecha actual
        $date = Carbon::now();
        $pay_day = $date->day;
        $pay_month = $date->month;
        $pay_year = $date->year;

        $marcas = SocialBrand::select('id','user_id','nombre','status',
            'conekta_id','conekta_customer_id','paypal_id',
            'pay_next_day','pay_next_month','pay_next_year','pay_amount',
            'tipo_pago','pago')
            ->whereHas('user', function ($query) {
                $query->where('tipo', 2)
                    ->where('status', 1);
            })
            ->where('id',$marca_id)
            ->get();

        if(count($marcas)==0){
            return response()->json([
                'error'=>'Marca no encontrada.',
            ], 404);
        }

        // return response()->json([
        //     'message'=>'Status ok',
        // ], 200);
        
        //Proxima fecha de cobro
        $date2 = Carbon::now()->addMonth();
        $pay_next_day = $date2->day;
        $pay_next_month = $date2->month;
        $pay_next_year = $date2->year;

        for ($i=0; $i < count($marcas); $i++) {

            if ($marcas[$i]->conekta_customer_id) {
                $marcas[$i]->pay_next_day = $pay_next_day;
                $marcas[$i]->pay_next_month = $pay_next_month;
                $marcas[$i]->pay_next_year = $pay_next_year;
                $marcas[$i]->pago = 0; //No pagada
                $marcas[$i]->save();
            
                $this->postOrderConektaTarjetaSave($marcas[$i]);
            }else{
                return response()->json([
                    'error'=>'La marca no tiene una tarjeta registrada para el cobro automático.',
                ], 409);
            }
            
        }

        // return response()->json([
        //     'marcas'=>$marcas,
        // ], 200);

        return response()->json([
            'message'=>'Cobro realizado.',
        ], 200);
    }

}
