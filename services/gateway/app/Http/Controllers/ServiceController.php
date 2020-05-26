<?php

namespace App\Http\Controllers;

use App\Services\Service;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    /**
     * Regras de validação
     *
     * @var array
     */
    private array $rules = [
        'email'    => 'email|required|max:50',
        'password' => 'string|required'
    ];

    /**
     * Função para logar um usuário e então enviar a requisição ao serviço responsável
     *
     * @param string $service
     * @param Request $request
     * @return JsonResponse
     */
    public function handler(string $service, Request $request): JsonResponse
    {
        // Recupero os dados
        $data = $request->all();
        
        // Se a validação dos dados falhar, já retorno
        $validator = Validator::make($data, $this->rules);
        if ($validator->fails()) {
            $fails = $validator->errors()->all();
            return response()->json([
                'status'  => 'Bad Request',
                'message' => 'Parâmetros inválidos',
                'fails'   => $fails
            ], 400);
        }

        // Tento efetutar o login desse usuário
        $loggedUser = $this->login($data['email'], $data['password']);

        // Se o login resultar em erro ou falhar, já retorno
        if ($loggedUser instanceof JsonResponse) {
            return $loggedUser;
        }

        // Service e login válidos, então chamo o service e retorno sua resposta
        return $this->callService($service, $loggedUser, $request);
    }

    /**
     * Tenta efetuar o login do usuário
     *
     * @param string $email
     * @param string $password
     * @return array
     */
    private function login(string $email, string $password): array
    {
        // Recupero as configurações do service
        $url = config('services.users.url');
        $key = config('services.users.key');

        // Efetuo a requisição de login
        $response = (new UserService($url, $key))->login($email, $password);

        // Se houver um erro, retorno-o
        if ($response['statusCode'] != 200) {
            return response()->json(
                Arr::except($response, 'statusCode'),
                $response['statusCode']
            );
        }

        // senão, retorno o usuário (jwt decodificado)
        return $response['user'];
    }

    /**
     * Passa a requisição recebida ao service realmente responsável por ela
     *
     * @param string $service
     * @param array $user
     * @param Request $request
     * @return JsonResponse
     */
    private function callService(
        string $service,
        array $user,
        Request $request
    ): JsonResponse {
        // Recupero os dados da requisição
        $data     = $request->all();
        $method   = $request->getMethod();
        $endpoint = $request->getPathInfo();

        // Recuperos as configurações do service
        $url = config("services.$service.url");
        $key = config("services.$service.key");

        // Executo a chamada
        $service  = new Service($url, $key, $user);
        $response = $service->request($endpoint, $data, $method);

        // E então a retorno
        return response()->json(
            Arr::except($response, 'statusCode'),
            $response['statusCode']
        );
    }
}
