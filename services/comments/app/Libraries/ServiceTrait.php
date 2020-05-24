<?php

namespace App\Libraries;

use Firebase\JWT\JWT;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException as GuzzleException;

use Illuminate\Support\Carbon;

/**
 * Trait para realizar requests em outros services
 * 
 * @author Guilherme Alves <guihalves20@gmail.com>
 */
trait ServiceTrait
{
    /**
     * URL do service
     * 
     * @var string
     */
    private string $url;

    /**
     * JWT Secret Key do service
     * 
     * @var string
     */
    private string $secret;

    /**
     * Armazena o usuário que fez login (payload do JWT)
     *
     * @var Object
     */
    private Object $user;

    public function __construct(string $serviceURL, string $jwtSecret, Object $user)
    {
        $this->url    = $serviceURL;
        $this->secret = $jwtSecret;
        $this->user   = $user;
    }

    /**
     * Realiza uma requisição ao service
     *
     * @param string $url
     * @param array $data
     * @param string $method
     * @return array
     */
    public function request(string $url, string $secretKey, array $data = [], string $method = 'POST'): array
    {
        $jwt = $this->generateJWT($secretKey);
        $client = new GuzzleClient();

        try {
            $response = $client->request($method, $url, [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $jwt
                ],
            ])->getBody();
        } catch (GuzzleException $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        $response = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $response;
    }

    /**
     * Gera um Token JWT
     *
     * @return string
     */
    public function generateJWT(string $secretKey): string
    {
        $now = Carbon::now();
        $payload = [
            'iat' => $now->unix(),
            'exp' => $now->add(config('jwt.expireAfter'))->unix(),
        ];

        if ($this->user) {
            $payload['user'] = $this->user;
        }

        return JWT::encode($payload, $secretKey);
    }
}
