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

    public function generateJWT(string $secretKey): string
    {
        $now = Carbon::now();
        $payload = [
            'iat' => $now->unix(),
            'exp' => $now->add(config('jwt.expireAfter'))->unix(),
        ];

        return JWT::encode($payload, $secretKey);
    }

    /**
     * Retorna o saldo de um usuário
     *
     * @return float
     */
    public function getBalance(): float
    {
        // TODO: call POST /balance
        return 500.00;
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
        // TODO: call POST /transaction
        // $transaction = [
        //     'comment_id' => $commentID,
        //     'coins' => $coins,
        //     'type' => 'out'
        // ];
        // $this->request()
        return 1;
    }

    /**
     * Confirma uma transaction
     *
     * @param integer $transactionID
     * @return boolean
     */
    public function confirm(int $id): bool
    {
        // TODO: call /transaction/confirm/$id
        return true;
    }
}
