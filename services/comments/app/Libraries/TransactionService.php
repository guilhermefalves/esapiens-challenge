<?php

namespace App\Libraries;

use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;

use Firebase\JWT\JWT;

/**
 * Classe para realizar operações no Service de Transactions
 * 
 * @author Guilherme Alves <guihalves20@gmail.com>
 */
class TransactionService
{
    use ServiceTrait;

    /**
     * Retorna o saldo de um usuário
     *
     * @return float
     */
    public function getBalance(): float
    {
        $response = $this->request('/balance');
        return $response['userBalance'];
    }

    /**
     * Cria uma transaction (dando destaque) para o commentID e retorna seu ID
     *
     * @param integer $commentID
     * @param integer $coins
     * @return integer
     */
    public function create(int $commentID, int $coins): int
    {
        $transaction = [
            'comment_id' => $commentID,
            'coins' => $coins,
            'type' => 'out'
        ];
        $response = $this->request('/transaction', $transaction);
        return $response['id'];
    }

    /**
     * Confirma uma transaction
     *
     * @param integer $transactionID
     * @return boolean
     */
    public function confirm(int $id): bool
    {
        return $this->requestStatus('/transaction/confirm/' . $id) == 200;
    }
}
