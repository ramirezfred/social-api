<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\SocialImage;

//use Hash;
use DB;
//use Validator;
use Exception;


use Carbon\Carbon;


date_default_timezone_set('America/Mexico_City');

class ApiMetricoolController extends Controller
{
    public function publicar(Request $request)
    {
        
    }
}
