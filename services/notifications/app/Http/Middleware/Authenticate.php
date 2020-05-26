<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\{JWT, SignatureInvalidException};
use Illuminate\Http\JsonResponse;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (!$request->header('Authorization')) {
            return $this->return401();
        }

        $jwt = str_replace('Bearer ', '', $request->header('Authorization'));
        try {
            JWT::decode($jwt, config('jwt.key'), config('jwt.alg'));
        } catch (SignatureInvalidException $e) {
            return $this->return401();
        }

        return $next($request);
    }

    /**
     * Return an 401 error
     *
     * @return JsonResponse
     */
    private function return401(): JsonResponse
    {
        return response()->json([
            'status' => 'Unauthorized',
            'message' => 'Falha no Login'
        ], 401);
    }
}
