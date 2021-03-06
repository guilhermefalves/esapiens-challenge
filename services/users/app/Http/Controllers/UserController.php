<?php

namespace App\Http\Controllers;

use App\Models\User;
use LumenBaseCRUD\Controller as BaseCRUD;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\{Request, JsonResponse};

/**
 * Controller dos usuários
 * 
 * @author Guilherme Alves <guihalves20@gmail.com>
 */
class UserController extends BaseCRUD
{
    protected $model = User::class;
    protected array $postRules = [
        'name'       => 'string|required|max:100',
        'email'      => 'email|unique:users|required|max:50',
        'password'   => 'string|required',
        'subscriber' => 'boolean'
    ];

    protected array $putRules = [
        'name'       => 'string|max:100',
        'email'      => 'string|max:50',
        'password'   => 'string',
    ];

    /**
     * Executada antes da criação de um objeto
     * Uso-a para hashear a senha
     *
     * @param array $data dados do objeto
     * @return JsonResponse|void
     */
    protected function preStore(array &$data)
    {
        $data['password'] = Hash::make($data['password']);
    }

    /**
     * A partir de um usuário e senha retorna uma JWT para o usuário
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'email'    => 'string|required|max:50',
            'password' => 'string|required',
        ]);

        if ($validator->fails()) {
            $fails = $validator->errors()->all();
            return $this->response(400, compact('fails'), 'Parâmetros inválidos');
        }

        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return $this->response(403, [], 'Usuário ou senha inválidos');
        }

        $jwt = $this->generateJWT($user);
        return $this->response(200, compact('jwt'), "Logged");
    }

    /**
     * Verifica e retorna se um usuário é assinante
     *
     * @param integer $id
     * @return JsonResponse
     */
    public function subscriber(int $id): JsonResponse
    {
        $user = User::where('id', $id)->get('subscriber')->first();

        if (!$user) {
            return $this->response(404, [], 'Usuário não encontrado');
        }

        $subscriber = (bool) $user->subscriber;
        return $this->response(200, ['subscriber' => $subscriber]);
    }

    /**
     * A partir de um password gera um token JWT
     *
     * @see https://jwt.io/
     * @param string $pass
     * @return string
     */
    private function generateJWT(User $user): string
    {
        // Header contendo o tipo do token (JWT) e o alroritmo (HS256)
        $header = $this->encode([
            'typ' => 'JWT',
            'alg' => 'HS256',
        ]);

        // Informações adicionais (payload)
        $now = Carbon::now();
        $payload  = $this->encode([
            'user' => $user->toArray(),
            'iat'  => $now->unix(),
            'exp'  => $now->add(config('jwt.expireAfter'))->unix()
        ]);

        $headerDotPayload = sprintf('%s.%s', $header, $payload);
        $password  = config('jwt.key');
        $signature = hash_hmac('sha256', $headerDotPayload, $password, true);
        $signature = $this->encode($signature);
    
        return sprintf('%s.%s.%s', $header, $payload, $signature);
    }

    /**
     * Função para encodar um string/array em base64
     * Utilizada para gerar o token JWT
     *
     * @param array|string $toEncode
     * @return string
     */
    private function encode($toEncode): string
    {
        $encoded = (is_array($toEncode)) ? json_encode($toEncode) : $toEncode;
        $encoded = base64_encode($encoded);
        $encoded = str_replace(['+', '/', '='], ['-', '_', ''], $encoded);
        return $encoded;
    }

    /**
     * OpenAPI - Docs - Endpoints in BaseCRUD
     * @OA\Post(
     *     path="/users",
     *     summary="Cria um usuário no sistema",
     *     tags={"Users"},
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Nome do usuário",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="E-mail do usuário - Usado como login",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         description="Senha do usuário",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="subscriber",
     *         in="query",
     *         description="Identifica se o usuário é assinante (default: false)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Dados inválidos"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Autorização negada"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erro ao exibir notificação"
     *     )
     * )
     */
}
