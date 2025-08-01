<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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

date_default_timezone_set('America/Mexico_City');

class SistemaController extends Controller
{
    public function index()
    {
        $objs = Sistema::all();

        if(count($objs)==0){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Sistema no configurado'], 409);
        }

        return response()->json(['sistema'=>$objs[0]], 200);
    }

    public function store(Request $request)
    {
        
        $objs = Sistema::all();
        if(count($objs)!=0){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'El sistema ya está configurado'], 409);
        }

        if($nuevoObj=Sistema::create([
            'costo_marca'=>$request->input('costo_marca'),
            'key_1'=>$request->input('key_1'),
            'key_2'=>$request->input('key_2'),
            'key_3'=>$request->input('key_3'),
            
        ])){

            return response()->json(['sistema'=>$nuevoObj, 'message'=>'Sistema configurado con éxito.'], 200);
        }else{
            return response()->json(['error'=>'Error al configurar el sistema.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.
        $sistema = Sistema::find($id);

        if (!$sistema)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el registro del sistema con id '.$id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $costo_marca=$request->input('costo_marca'); 
        $key_1=$request->input('key_1');
        $key_2=$request->input('key_2'); 
        $key_3=$request->input('key_3');
        $prompt_textos=$request->input('prompt_textos');
        $prompt_escenaA=$request->input('prompt_escenaA');
        $prompt_escenaB=$request->input('prompt_escenaB'); 
        $prompt_imagenesA=$request->input('prompt_imagenesA');
        $prompt_imagenesB=$request->input('prompt_imagenesB'); 
        $dalle=$request->input('dalle');
        $costo_bot=$request->input('costo_bot'); 

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.

        if (($costo_marca != null && $costo_marca!='') || $costo_marca===0)
        {
            $sistema->costo_marca = $costo_marca;
            $bandera=true;
        }

        if ($key_1 != null && $key_1!='')
        {
            $sistema->key_1 = $key_1;
            $bandera=true;
        }

        if ($key_2 != null && $key_2!='')
        {
            $sistema->key_2 = $key_2;
            $bandera=true;
        }

        if ($key_3 != null && $key_3!='')
        {
            $sistema->key_3 = $key_3;
            $bandera=true;
        }

        // Actualización parcial de campos.
        if ($prompt_textos != null && $prompt_textos!='')
        {

            //verificar la presencia de <cantidad>
            $posicionA = strpos($prompt_textos, '<');
            if ($posicionA === false) {
                return response()->json(['error'=>'La cantidad debe ser numérica y debe ir entre <>'], 409);
            }

            $posicionB = strpos($prompt_textos, '>');
            if ($posicionB === false) {
                return response()->json(['error'=>'La cantidad debe ser numérica y debe ir entre <>'], 409);
            }

            //validar cantidad entera
            $cantidad = substr($prompt_textos,$posicionA+1,$posicionB-($posicionA+1));
            if (!ctype_digit($cantidad)) {
                return response()->json(['error'=>'La cantidad debe ser entera'], 409);
            }

            $cantidad = intval($cantidad);
            if ($cantidad < 1 || $cantidad > 10) {
                return response()->json(['error'=>'La cantidad debe estar entre 1 y 10'], 409);
            }

            //verificar la precencia de {{marca}}
            $marca = strpos($prompt_textos, '{{marca}}');
            if ($marca === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{marca}}'], 409);
            }

            //verificar la precencia de {{servicios}}
            $servicios = strpos($prompt_textos, '{{servicios}}');
            if ($servicios === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{servicios}}'], 409);
            }


            $sistema->prompt_textos = $prompt_textos;
            $bandera=true;
        }

        if ($prompt_escenaA != null && $prompt_escenaA!='')
        {
            //verificar la precencia de {{texto}}
            $texto = strpos($prompt_escenaA, '{{texto}}');
            if ($texto === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{texto}}'], 409);
            }

            $sistema->prompt_escenaA = $prompt_escenaA;
            $bandera=true;
        }

        if ($prompt_escenaB != null && $prompt_escenaB!='')
        {
            //verificar la precencia de {{texto}}
            $texto = strpos($prompt_escenaB, '{{texto}}');
            if ($texto === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{texto}}'], 409);
            }

            //verificar la precencia de {{parametros}}
            $parametros = strpos($prompt_escenaB, '{{parametros}}');
            if ($parametros === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{parametros}}'], 409);
            }

            $sistema->prompt_escenaB = $prompt_escenaB;
            $bandera=true;
        }

        if ($prompt_imagenesA != null && $prompt_imagenesA!='')
        {
            //verificar la precencia de {{escena}}
            $escena = strpos($prompt_imagenesA, '{{escena}}');
            if ($escena === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{escena}}'], 409);
            }

            $sistema->prompt_imagenesA = $prompt_imagenesA;
            $bandera=true;
        }

        if ($prompt_imagenesB != null && $prompt_imagenesB!='')
        {
            //verificar la precencia de {{escena}}
            $escena = strpos($prompt_imagenesB, '{{escena}}');
            if ($escena === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{escena}}'], 409);
            }

            //verificar la precencia de {{parametros}}
            $parametros = strpos($prompt_imagenesB, '{{parametros}}');
            if ($parametros === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{parametros}}'], 409);
            }

            $sistema->prompt_imagenesB = $prompt_imagenesB;
            $bandera=true;
        }

        if (($dalle != null && $dalle!='') || $dalle===0)
        {
            $sistema->dalle = $dalle;
            $bandera=true;
        }

        if (($costo_bot != null && $costo_bot!='') || $costo_bot===0)
        {
            $sistema->costo_bot = $costo_bot;
            $bandera=true;
        }
        
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($sistema->save()) {
                
                return response()->json(['message'=>'Sistema configurado con éxito.',
                    'sistema'=>$sistema], 200);
            }else{
                return response()->json(['error'=>'Error al configurar el sistema.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al sistema.'],409);
        }
    }

    public function costoMarca()
    {
        $objs = Sistema::all();

        if(count($objs)==0){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Sistema no configurado'], 409);
        }

        return response()->json(['costo_marca'=>$objs[0]->costo_marca], 200);
    }

    public function updateTest(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.
        $sistema = Sistema::find($id);

        if (!$sistema)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el registro del sistema con id '.$id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $prompt_textos=$request->input('prompt_textos');
        $prompt_escenaA=$request->input('prompt_escenaA');
        $prompt_escenaB=$request->input('prompt_escenaB');  

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if ($prompt_textos != null && $prompt_textos!='')
        {

            //verificar la presencia de <cantidad>
            $posicionA = strpos($prompt_textos, '<');
            if ($posicionA === false) {
                return response()->json(['error'=>'La cantidad debe ser numérica y debe ir entre <>'], 409);
            }

            $posicionB = strpos($prompt_textos, '>');
            if ($posicionB === false) {
                return response()->json(['error'=>'La cantidad debe ser numérica y debe ir entre <>'], 409);
            }

            //validar cantidad entera
            $cantidad = substr($prompt_textos,$posicionA+1,$posicionB-($posicionA+1));
            if (!ctype_digit($cantidad)) {
                return response()->json(['error'=>'La cantidad debe ser entera'], 409);
            }

            $cantidad = intval($cantidad);
            if ($cantidad < 1 || $cantidad > 10) {
                return response()->json(['error'=>'La cantidad debe estar entre 1 y 10'], 409);
            }

            //verificar la precencia de {{marca}}
            $marca = strpos($prompt_textos, '{{marca}}');
            if ($marca === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{marca}}'], 409);
            }

            //verificar la precencia de {{servicios}}
            $servicios = strpos($prompt_textos, '{{servicios}}');
            if ($servicios === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{servicios}}'], 409);
            }


            $sistema->prompt_textos = $prompt_textos;
            $bandera=true;
        }

        if ($prompt_escenaA != null && $prompt_escenaA!='')
        {
            //verificar la precencia de {{texto}}
            $texto = strpos($prompt_escenaA, '{{texto}}');
            if ($texto === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{texto}}'], 409);
            }

            $sistema->prompt_escenaA = $prompt_escenaA;
            $bandera=true;
        }

        if ($prompt_escenaB != null && $prompt_escenaB!='')
        {
            //verificar la precencia de {{texto}}
            $texto = strpos($prompt_escenaB, '{{texto}}');
            if ($texto === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{texto}}'], 409);
            }

            //verificar la precencia de {{parametros}}
            $parametros = strpos($prompt_escenaB, '{{parametros}}');
            if ($parametros === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{parametros}}'], 409);
            }

            $sistema->prompt_escenaB = $prompt_escenaB;
            $bandera=true;
        }
       
        if ($bandera)
        {
            return response()->json(['message'=>'Sistema configurado con éxito.',
                    'sistema'=>$sistema], 200);
        }
        else
        {
            return response()->json(['error'=>'No se ha modificado ningún dato al sistema.'],409);
        }
    }

    public function costoBot()
    {
        $objs = Sistema::all();

        if(count($objs)==0){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Sistema no configurado'], 409);
        }

        return response()->json(['costo_bot'=>$objs[0]->costo_bot], 200);
    }
}
