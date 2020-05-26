<?php

namespace App\Libraries;

/**
 * Classe para realizar operações no Service de notificações
 * 
 * @author Guilherme Alves <guihalves20@gmail.com>
 */
class NotificationService
{
    use ServiceTrait;

    /**
     * Cria uma notificação no service de notificações e retorna seu ID
     *
     * @return integer
     */
    public function create(
        int $receiverID,
        string $receiverEmail,
        string $message,
        int $identifier = 0
    ): int
    {
        // Crio um array com os dados da notificação
        $notification = [
            'to'         => $receiverID,
            'mail_to'    => $receiverEmail,
            'content'    => $message
        ];

        // Se passado, adiciono o identifier
        if ($identifier) {
            $notification['identifier'] = $identifier; 
        }

        // Salvo-a e retorno seu ID
        $response = $this->request('/notifications', $notification);
        return (isset($response['id'])) ? $response['id'] : 0;
    }

    /**
     * Deleta uma notificação no service de notificações
     *
     * @return boolean
     */
    public function delete(int $id): bool
    {
        $statusCode = $this->requestStatus('/notifications/' . $id, [], 'DELETE');
        return $statusCode == 200;
    }
}
