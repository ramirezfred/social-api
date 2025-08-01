<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\Sistema;

use DB;

use Exception;

trait ApiPayPalTrait
{
    //produccion
    // public static $base_url_paypal = "https://api-m.paypal.com";
    // public static $access_token_paypal = "";
    // public static $CLIENT_ID = "Ac3Y6r6D4WewfUKd-GPqAkxO-c_9dpFQKqdeg9zHUtPHOOXc5WTY1qA7HmQRE4YQIponVQAsy6JMH05L";
    // public static $SECRET = "EE7PT0uKvRtNGQ1lqjUotIFI1T15J5taTCCetfHEJ9Ll_8DMIwSwwBIgZCuoQuz6XpCvzRFYnxufnBrZ";

    //pruebas freddy
    public static $base_url_paypal = "https://api-m.sandbox.paypal.com";
    public static $access_token_paypal = "";
    public static $CLIENT_ID = "AYDm5MoqD21AUPQNoKQSWst_d1L9uB9HuD88Ak_zO_UOBoYKVDvuHtM3vTt7UpFjN0L8vwZ5f0q6J7Ry";
    public static $SECRET = "ENBQEwpfSpjDg3rz6jVtrH2NgGu717f_T2FEBs_i4hIEqimFQM7TKSM2muVcZ989n0lFBrWWn8Rre0z3";

    public static function _login()
    {
             
        //Armando la peticion cURL
        $fields = "grant_type=client_credentials";
            
        //$fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_paypal."/v1/oauth2/token");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Accept-Language: en_US",
            "Content-Type: application/x-www-form-urlencoded",
            //"Authorization: Basic ".$this->CLIENT_ID.":".$this->SECRET,
            "Authorization: Basic ".base64_encode(static::$CLIENT_ID.":".static::$SECRET),
            
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con PayPal',
                'paypal'=>$err
            ];
        } else {

            $paypal_obj = json_decode($response);

            if ($paypal_obj) {
                if (!property_exists($paypal_obj, 'error')) {

                    static::$access_token_paypal = $paypal_obj->access_token;

                    return [
                        'status'=>200,
                        'paypal'=>$paypal_obj
                    ];

                }else{
                    return [
                        'status'=>409,
                        'error'=>$paypal_obj->error,
                        'paypal'=>$paypal_obj
                    ];
                }

                
            }else{
                return [
                    'status'=>409,
                    'error'=>'Error en Data',
                    'paypal'=>$response
                ];
            }
             
        }  

    }

    public function _getSubscription($subscription_id)
    {

        $this->_login();
             
        //Armando la peticion cURL

        $fields = array(  
        );

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_paypal."/v1/billing/subscriptions/".$subscription_id);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: Bearer ".static::$access_token_paypal,
            
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //curl_setopt($ch, CURLOPT_POST, TRUE);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con PayPal',
                'paypal'=>$err
            ];
        } else {

            $paypal_obj = json_decode($response);

            if ($paypal_obj) {
                if (!property_exists($paypal_obj, 'error')) {

                    return [
                        'status'=>200,
                        'paypal'=>$paypal_obj
                    ];

                }else{
                    return [
                        'status'=>409,
                        'error'=>$paypal_obj->error,
                        'paypal'=>$paypal_obj
                    ];
                }

                
            }else{
                return [
                    'status'=>409,
                    'error'=>'Error en Data',
                    'paypal'=>$response
                ];
            }
                  
          
        } 

    }

    public function _cancelarSubscription($subscription_id)
    {

        $this->_login();
             
        //Armando la peticion cURL

        $fields = array(
            "reason" => "Delete de marca o cambio de metodo de pago"  
        );

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_paypal."/v1/billing/subscriptions/".$subscription_id."/cancel");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: Bearer ".static::$access_token_paypal,
            
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
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con PayPal',
                'paypal'=>$err
            ];
        } else {

            //$paypal_obj = json_decode($response);

            return [
                'status'=>200,
                'paypal'=>$response
            ];
                  
          
        } 

    }

   

}
