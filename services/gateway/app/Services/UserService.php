<?php

namespace App\Services;

use Firebase\JWT\JWT;

/**
 * Classe para realizar requisições no service de usuários
 * 
 * @author Guilherme Alves <guihalves20@gmail.com>
 */
class UserService extends Service
{
    public function __construct(string $serviceURL, string $jwtSecret)
    {
        parent::__construct($serviceURL, $jwtSecret, []);
    }

    /**
     * A partir de um email e senha, tento fazer o login
     *
     * @param string $email
     * @param string $password
     * @return array
     */
    public function login(string $email, string $password): array
    {
        $response = $this->request('/login', compact(['email', 'password']), 'POST');

        if ($response['statusCode'] != 200) {
            return $response;
        }

        $secret  = config('services.users.key');
        $decoded = JWT::decode($response['jwt'], $secret, config('jwt.alg'));

        return [
            'statusCode' => 200,
            'user' => (array) $decoded->user
        ];
    }
}
