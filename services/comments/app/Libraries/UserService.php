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
        $this->user   = (object) [];
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

    /**
     * A partir de um ID, busca e retorna um usuário
     *
     * @param integer $id
     * @return object
     */
    public function get(int $id): object
    {
        $response = $this->request('/users/' . $id, [], 'GET');
        $response = (isset($response['data'])) ? $response['data'] : [];
        return (object) $response;
    }

}
