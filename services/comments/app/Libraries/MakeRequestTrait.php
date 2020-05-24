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
trait MakeRequestTrait
{
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

        return JWT::encode($payload, $secretKey);
    }
}
