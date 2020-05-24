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
    public function create(): int
    {
        // TODO: call POST /notification
        return 1;
    }

    /**
     * Deleta uma notificação no service de notificações
     *
     * @return boolean
     */
    public function delete(int $id): bool
    {
        // TODO: call POST /notification/$id
        return true;
    }
}
