<?php

namespace App\Services;

use Firebase\JWT\JWT;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Carbon;

/**
 * Classe para realizar operações no Service de usuários
 * 
 * @author Guilherme Alves <guihalves20@gmail.com>
 */
class Service
{
    private string $url;
    private string $secret;

    public function __construct(string $serviceURL, string $jwtSecret, array $user)
    {
        $this->url    = $serviceURL;
        $this->secret = $jwtSecret;
        $this->user   = $user;
    }

    /**
     * Realiza uma requisição ao service
     *
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @param boolean $returnStatusCode
     * @return array|integer
     */
    public function request(
        string $endpoint,
        array $data = [],
        string $method = 'GET'
    ): array {
        // Seto os dados básicos da requisição
        $url  = $this->url . $endpoint;
        $json = $data;
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->generateJWT()
        ];

        $statusCode = $response = null;
        try {
            // Tento realizar o request
            $client = new GuzzleClient();
            $result = $client->request($method, $url, compact(['json', 'headers']));
        } catch (RequestException $e) {
            $statusCode = $e->getCode();
            $response   = $e->getResponse()->getBody();
        }

        $statusCode = ($statusCode) ? $statusCode : $result->getStatusCode();
        $response   = ($response)   ? $response   : $result->getBody();
        $response   = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'statusCode' => 500,
                'status'     => 'Erro',
                'message'    => 'JSON de resposta inválido'
            ];
        }

        return array_merge([
            'statusCode' => $statusCode,
            'status'     => 'OK',
            'message'    => ''
        ], $response);
    }

    /**
     * Gera um Token JWT
     *
     * @return string
     */
    public function generateJWT(): string
    {
        $now = Carbon::now();
        $payload = [
            'iat' => $now->unix(),
            'exp' => $now->add(config('jwt.expireAfter'))->unix(),
        ];

        if ($this->user) {
            $payload['user'] = $this->user;
        }

        return JWT::encode($payload, $this->secret);
    }
}
