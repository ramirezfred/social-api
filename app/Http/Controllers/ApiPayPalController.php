<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Crypt;

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

class ApiPayPalController extends Controller
{
    use ApiPayPalTrait;

    public function test()
    {
        //$resp = $this->_login();
        //$resp = $this->_cancelarSubscription("I-H0PLAYSKMLM1");
        //$resp = $this->_getSubscription("I-H0PLAYSKMLM1");
        //$resp = $this->_cancelarSubscription("I-CPE7PPPBFCXR");
        $resp = $this->_getSubscription("I-CPE7PPPBFCXR");
        if ($resp['status'] == 200) {
            return response()->json([
                'paypal'=>$resp
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'paypal'=>$resp['paypal']
            ], $resp['status']); 
        }
    }

    public function order(Request $request)
    {
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

        //$resp = $this->_getSubscription("I-H0PLAYSKMLM1");
        $resp = $this->_getSubscription($request->input('subscriptionID'));
        if ($resp['status'] == 200) {

            // return response()->json([
            //     'paypal'=>$resp
            // ], $resp['status']);

            if ($resp['paypal']->status == "ACTIVE") {
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

                //setear pago con paypal
                $marca->conekta_customer_id = null;
                $marca->paypal_subscription_id = $request->input('subscriptionID');
                $marca->tipo_pago = 2;

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

                return response()->json([
                    'message'=>'Subscripción creada con éxito.',
                    'marca'=>$marca
                ], 200);

            }else{
                //Desactivar la marca con pago declinado
                $marca->status = 0;
                $marca->pago = 0; //No Pagada
                $marca->save();

                return response()->json([
                    'error'=>'Error al activar la subscripción',
                    'paypal'=>$resp['paypal'],
                ], 200);

            }

        }else{
           return response()->json([
                'error'=>$resp['error'],
                'paypal'=>$resp['paypal']
            ], $resp['status']); 
        }
    }
}
