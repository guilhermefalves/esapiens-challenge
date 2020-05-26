<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Valida se o serviço e a entidade são válidos
 * 
 * @author Guilherme Alves <guilherme.alves@jurid.com.br>
 */
class ValidService
{
    /**
     * Serviços disponíveis na API
     * 
     * @var array
     */
    public array $services = [
        'comments',
        'notifications'
    ];

    /**
     * Valida se o service requisitado é válido
     *
     * @param string $service
     * @return boolean
     */
    private function isValidService(string $service): bool
    {
        return in_array($service, $this->services);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Verifica se o serviço está nos disponíveis
        if (!$this->isValidService($request->service)) {
            return response()->json([
                'status'  => 'Not Found',
                'message' => 'Serviço não encontrato'
            ], 404);
        }

        return $next($request);
    }
}
