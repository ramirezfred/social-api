<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use DB;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

class AuthController extends Controller
{

    public function loginWeb(Request $request)
    {
        $credentials = request(['email', 'password']);
        $token = null;
        $user = null;

        try {

            $user = User::where('email', $request->input('email'))->first();
            if (empty($user)) {
                return response()->json(['error' => 'Email inválido.'], 401);
            }

            //Validar el usuario
            // if ($user->tipo_usuario != 1 && $user->tipo_usuario != 2) {
            //     return response()->json(['error' => 'Credenciales inválidas.'], 401);
            // }

            if (! $token = auth()->attempt($credentials)) {
                return response()->json(['error' => 'Password inválido.'], 401);
            }

            //$user = JWTAuth::toUser($token);
            
        } catch (JWTException $ex) {
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        //return response()->json(compact('token', 'user'));

        /* return response()
            ->json([
                'token' => $token,
                'user' => $user
            ]); */

        $user->last_login = now();
        $user->save();

        return $this->respondWithToken($token);
    }

        /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    public function getAuthenticatedUser()
    {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['user_not_found'], 404);
            }
        } catch (Exception $e) {
            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException){
                return response()->json(['error' => 'Token is Invalid'], 401);
            }else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException){
                return response()->json(['error' => 'Token is Expired'], 401);
            }else{
                return response()->json(['error' => 'Authorization Token not found'], 401);
            }
        }
            return response()->json(compact('user'));
    }

}
