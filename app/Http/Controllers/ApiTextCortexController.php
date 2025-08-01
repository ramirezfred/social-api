<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Sistema;

//use Hash;
use DB;
//use Validator;
use Exception;


use Carbon\Carbon;

use App\Http\Traits\ApiTextCortexTrait;

date_default_timezone_set('America/Mexico_City');

class ApiTextCortexController extends Controller
{
    use ApiTextCortexTrait;

    public function completions(Request $request)
    {
        $resp = $this->_completions($request->input('bot_id'),$request->input('mensaje'));
        if ($resp['status'] == 200) {
            return response()->json([
                'textcortex'=>$resp
            ], $resp['status']);
        }else{
           return response()->json([
                //'error'=>$resp['error'],
                'textcortex'=>$resp['textcortex']
            ], $resp['status']); 
        }
    }
}
