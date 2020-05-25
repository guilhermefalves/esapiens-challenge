<?php

namespace App\Http\Controllers;

use App\Models\Transaction;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

use LumenBaseCRUD\APIResponse;
use Firebase\JWT\JWT;

/**
 * Controller das transactions
 * 
 * @author Guilherme Alves <guihalves20@gmail.com>
 */
class TransactionController extends Controller
{
    use APIResponse;

    /**
     * Regras de validação para POST
     */
    private array $validationRules = [
        'comment_id' => 'integer|required_if:type,out',
        'coins'      => 'integer|required|min:1',
        'type'       => 'in:in,out|required'
    ];

    /**
     * Salva uma transaction para um usuário
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->all();
        
        // Se os dados forem inválidos, já retorno
        $validator = Validator::make($data, $this->validationRules);
        if ($validator->fails()) {
            $fails = $validator->errors()->all();
            return $this->response(400, compact('fails'), 'Parâmetros inválidos');
        }

        // Recupero o user do token JWT
        $auth = $request->header('Authorization');
        $user = $this->getUserFromAuthHeader($auth); 
        $data['user_id'] = $user->id;

        // Preencho alguns campos padrões
        $this->setDefaultDataFields($data);

        // Verifico se o usuário tem créditos suficientes para a transaction, ou
        // seja, a transaction + taxa do sistema
        $requiredCoins = abs($data['coins']) * (1 + $data['tax']);
        $requiredCoins = ($data['type'] == 'in') ? 0 : $requiredCoins;
        if (!$this->hasBalance($user->id, $requiredCoins)) {
            return $this->response(402, [], 'Créditos insuficientes');
        }

        // Crio a transaction e se ocorrer algum erro, retorno
        $created = Transaction::create($data);
        if (!$created) {
            return $this->response(500);
        }

        if ($created->type == 'in') {
            return $this->response(201, ['id' => $created->id]);
        }

        // E por fim, crio a transaction do sistema
        return $this->createSystemTransaction($created);
    }

    /**
     * Retorna o saldo de um usuário
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function balance(Request $request): JsonResponse
    {
        // Recupero o user do token JWT
        $auth = $request->header('Authorization');
        $user = $this->getUserFromAuthHeader($auth);

        $userBalance = $this->getUserBalance($user->id);

        // Retorna o saldo do usuário já descontando as taxas
        $systemTax   = 1 - config('app.systemTax');
        $userBalance = $userBalance * $systemTax;
        
        return $this->response(200, compact('userBalance'));
    }

    /**
     * Confirma uma transaction
     *
     * @param integer $id
     * @param Request $request
     * @return JsonResponse
     */
    public function confirm(int $id, Request $request): JsonResponse
    {
        // Recupero o user do token JWT
        $auth = $request->header('Authorization');
        $user = $this->getUserFromAuthHeader($auth);

        // Busco a transaction para validar se posso confirma-la
        $transaction = Transaction::find($id);

        // Não posso validar transactions de outro usuário
        if ($transaction->user_id != $user->id) {
            return $this->response(403, [], 'Não é possível confirmar essa transaction');
        }

        // nem que são do sistema
        if ($transaction->system_transaction) {
            return $this->response(403, [], 'Não é possível confirmar essa transaction');
        }

        // nem que são do tipo in
        if ($transaction->type == 'in') {
            return $this->response(403, [], 'Não é possível confirmar essa transaction');
        }

        // Confirmo a transaction com o id recebido e suas filhas (geradas pelo sistema)
        $updated = Transaction::where('id', $id)
            ->orWhere('transaction_id', $id)
            ->update(['confirmed' => true]);
        
        // Se houver erros no update
        if (!$updated) {
            return $this->response(500);
        }
        
        return $this->response(200, [], 'Transações confirmadas');
    }

    /**
     * Calcula alguns campos da transaction
     *
     * @param array $data
     * @return void
     */
    private function setDefaultDataFields(array &$data)
    {
        // Calculo as coins de acordo com o tipo
        $coins = abs($data['coins']);
        $data['coins'] = ($data['type'] == 'in') ? $coins : -$coins;

        // Se a transaction for uma recarga, já confirmo
        $data['confirmed'] = ($data['type'] == 'in');

        // Aplico a taxa do sistema
        $data['tax']   = config('app.systemTax') ?? 0;
    }

    /**
     * A partir de uma transaction do tipo out, crio uma transaction no sistema
     * com as devidas taxas
     *
     * @param Transaction $transaction
     * @return void
     */
    private function createSystemTransaction(Transaction $transaction)
    {
        $systemTransaction = [
            'user_id' => $transaction->user_id,
            'tax'     => 0,
            'coins'   => $transaction->coins * $transaction->tax,
            'type'    => 'out',
            'comment_id'     => $transaction->comment_id,
            'transaction_id' => $transaction->id,
            'system_transaction' => true
        ];

        // Tento criar a transaction e se objtiver erro, já retorno
        $created = Transaction::create($systemTransaction);
        if (!$created) {
            return $this->response(500, [], 'Problemas ao criar a transaction');
        }

        return $this->response(201, ['id' => $transaction->id]);
    }

    /**
     * Verifica se um usuário tem saldo suficiente para uma transaction
     *
     * @param integer $userID
     * @param float $requiredCoins
     * @return boolean
     */
    private function hasBalance(int $userID, float $requiredCoins): bool
    {
        // Se não houver a necessidade de coins, já retorno que há saldo
        if (!$requiredCoins) {
            return true;
        }

        // Busco o saldo do usuário
        $userBalance = $this->getUserBalance($userID);
        return $userBalance >= $requiredCoins;
    }

    /**
     * Busca e retorna o saldo de um usuário
     *
     * @param integer $userID
     * @return float
     */
    private function getUserBalance(int $userID): float
    {
        $userBalance = (float) Transaction::where('user_id', $userID)
            ->where(function($query) {
                // Todas as confirmadas
                $query->where('confirmed', true);

                // OU todas as que foram criadas a pouco tempo de acordo com 
                // app.notConfirmedTTL e ainda não foram confirmadas
                // Obs. passo essa responsabilidade para o banco para evitar erros
                // com timezone diferentes (php e db)
                $ttl = config('app.notConfirmedTTL');
                $raw = sprintf('created_at >= (select now() - interval %s)', $ttl);
                $query->orWhereRaw($raw);
            })
            ->sum('coins');

        return $userBalance;
    }

    /**
     * A partir do token de Authorization (JWT) recupero o user (payload)
     *
     * @param string $auth
     * @return object
     */
    private function getUserFromAuthHeader(string $auth): object
    {
        $jwt        = str_replace('Bearer ', '', $auth);
        $decodedJWT = JWT::decode($jwt, config('app.jwtKey'), ['HS256']);
        return $decodedJWT->user ?? (object) [];
    }
}