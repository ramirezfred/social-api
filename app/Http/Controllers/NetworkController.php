<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Crypt;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;

//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;

use App\Http\Traits\ApiMetaTrait;

date_default_timezone_set('America/Mexico_City');

class NetworkController extends Controller
{
    use ApiMetaTrait;

    public function misRedes($user_id)
    {
        $objs = socialNetwork::with('marca')
            ->whereHas('marca', function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            })
            ->get();

        return response()->json(['redes'=>$objs], 200);
    }

    public function index($brand_id)
    {
        $objs = socialNetwork::where('brand_id', $brand_id)->get();

        return response()->json(['redes'=>$objs], 200);
    }

    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'brand_id'=>'required|integer',
            'tipo'=>'required|integer',
            //'alias'=>'required|string',
            //'user'=>'required|string',
            //'password'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux = SocialBrand::find($request->input('brand_id'));
        if(!$aux){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'No existe la marca con id '.$request->input('brand_id')], 409);
        }

        $objSocialNetwork=SocialNetwork::
            where('page_id',$request->input('page_id'))
            ->first();
        if ($objSocialNetwork)
        {
            // Devolvemos error codigo http 409
            return response()->json(['error'=>'Esta Red Social ya está vinculada a una marca.'], 409);
        }

        if ($request->input('tipo')!=1 && $request->input('tipo')!=2 && $request->input('tipo')!=3) {
            // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Tipo inválido.'], 409);
        }

        $user_id = $aux->user_id;

        $alias = null;
        if($request->input('tipo')==1){$alias='Facebook';}
        if($request->input('tipo')==2){$alias='Instagram';}
        if($request->input('tipo')==3){$alias='Twitter';}

        $aux_2 = SocialNetwork::where('alias', $alias)
            ->whereHas('marca', function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            })
            ->where('brand_id',$request->input('brand_id'))
            ->get();
        if(count($aux_2)!=0){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Ya tienes otra red con ese alias.'], 409);
        }

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

        $claveAdicional = config('app.lada_b');

        $cadenaEncriptada = Crypt::encrypt($cadena, $claveAdicional);

        if($nuevoObj=SocialNetwork::create([
            'brand_id'=>$request->input('brand_id'),
            'tipo'=>$request->input('tipo'),
            'login'=>0,
            'alias'=>$alias,
            'user'=>$request->input('user'),
            'password'=>md5($request->input('password')),
            'meta_user_id'=>$request->input('meta_user_id'),
            'page_id'=>$request->input('page_id'),
            'page_name'=>$request->input('page_name'),
            'page_image'=>$request->input('page_image'),
            'access_token'=>$cadenaEncriptada,
        ])){

            $aux->horario = json_decode($aux->horario);
            $nuevoObj->marca = $aux;

            //suscribir al webhook
            if ($request->has('webhook_id'))
            {
                $resp = $this->_postSubscribedApps(
                    $request->input('webhook_id'),
                    $request->input('access_token'));
            }

            //Enviar Email
            $details = [
                'title' => 'Registro de Red Social',
                'body' => 'La marca '.$aux->nombre.', vinculó la Red Social '.$alias
            ];

            // \Mail::to('tonii.jaam@gmail.com')->send(new \App\Mail\RegistroRedEmail($details));
            //\Mail::to('ramirez.fred016@gmail.com')->send(new \App\Mail\RegistroRedEmail($details));

            return response()->json(['message'=>'Red social agregada con éxito.',
             'red'=>$nuevoObj], 200);
        }else{
            return response()->json(['error'=>'Error al agregar la red social.'], 500);
        }
    }

    public function destroy($id)
    {
        // Comprobamos si lo que nos están pasando existe o no.
        $obj=SocialNetwork::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la red con id '.$id], 404);
        }

        // Eliminamos.
        $obj->delete();

        return response()->json(['message'=>'Se ha eliminado correctamente la red.'], 200);
    }

}
