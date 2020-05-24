<?php

namespace App\Libraries;

/**
 * Classe para realizar operações no Service de usuários
 * 
 * @author Guilherme Alves <guihalves20@gmail.com>
 */
class UserService
{
    use ServiceTrait;

    public function __construct(string $serviceURL, string $jwtSecret)
    {
        $this->url    = $serviceURL;
        $this->secret = $jwtSecret;
    }

    /**
     * Verifica se um usuário (userID) é um assinante
     *
     * @param integer $userID
     * @return boolean
     */
    public function isSubscriber(int $userID): bool
    {
        $endpoint = '/is/subscriber/' . $userID;
        $response = $this->request($endpoint);

        return $response["subscriber"] ?? false;
    }

}
