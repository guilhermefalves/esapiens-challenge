<?php

namespace Tests;

use App\Models\Transaction;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;

class TransactionControllerTest extends TestCase
{
    use MigrateAfterTestsTrait;

    /**
     * Headers que serão enviados nos requests
     * @param array $headers
     */
    private array $headers = [];

    /**
     * Taxa do sistema cobrada sobre as operações do tipo out
     * @param string $systemTax
     */
    private string $systemTax;

    protected function setUp(): void
    {
        parent::setUp();
        $payload = [
            'iat' => Carbon::now()->format('U'),
            'exp' => Carbon::now()->addMinutes(2)->format('U'),
            'user' => [
                'id' => 1
            ]
        ];
        $jwt = JWT::encode($payload, config('app.jwtKey'));
        $this->headers['Authorization'] = 'Bearer ' . $jwt;

        $this->systemTax = config('app.systemTax');
    }

    public function testCreateIn(): Transaction
    {
        $transaction = factory(Transaction::class)->make([
            'type'    => 'in',
            'coins'   => 1000,
            'transaction_id'     => null,
            'system_transaction' => false
        ])->toArray();
        $transaction['coins'] = abs($transaction['coins']);

        $this->post('/transaction', $transaction, $this->headers)->seeJsonStructure([
            'message', 'status', 'id'
        ]);

        $this->response->assertStatus(201);

        $transaction = Transaction::find($this->response->getData()->id);
        $this->assertNotEmpty($transaction, 'transaction not created');

        return $transaction;
    }

    /**
     * @depends testCreateIn
     * @return Transaction
     */
    public function testCreateOut(): Transaction
    {
        $transaction = factory(Transaction::class)->make([
            'type'  => 'out',
            'coins' => 100,
            'transaction_id'     => null,
            'system_transaction' => false,
        ])->toArray();

        $this->post('/transaction', $transaction, $this->headers)->seeJsonStructure([
            'message', 'status', 'id'
        ]);

        $this->response->assertStatus(201);
        $transaction = Transaction::find($this->response->getData()->id);
        $this->assertEquals('out', $transaction->type);
        $this->assertFalse((bool) $transaction->confirmed);
        $this->assertNotEmpty($transaction, 'transaction out not created');

        // Também deve ter sido criada uma transaction do sistema
        $systemTransaction = Transaction::where('transaction_id', $transaction->id)->first();
        $this->assertNotEmpty($transaction, 'system transaction not created');

        $systemCoins = $transaction->coins * $this->systemTax;
        $this->assertEquals($systemCoins, $systemTransaction->coins);
        $this->assertEquals('out', $systemTransaction->type);
        $this->assertTrue((bool) $systemTransaction->system_transaction);
        $this->assertFalse((bool) $systemTransaction->confirmed);
        $this->assertEquals(0, $systemTransaction->tax);
        $this->assertEquals($transaction->user_id, $systemTransaction->user_id);
        $this->assertEquals($transaction->comment_id, $systemTransaction->comment_id);

        return $transaction;
    }

    public function testCreateOutNoBalance()
    {
        $transaction = factory(Transaction::class)->make([
            'type'               => 'out',
            'transaction_id'     => null,
            'coins'              => 1000,
            'system_transaction' => false
        ])->toArray();
        $transaction['coins'] = abs($transaction['coins']);

        $this->post('/transaction', $transaction, $this->headers)->seeJsonStructure([
            'message', 'status'
        ]);

        $this->response->assertStatus(402);
    }

    /**
     * @depends testCreateIn
     * @depends testCreateOut
     */
    public function testBalance(Transaction $transactionIn, Transaction $transactionOut)
    {
        $this->post('/balance', [], $this->headers)->seeJsonStructure([
            'status', 'message', 'userBalance'
        ]);

        $this->response->assertStatus(200);

        $systemCoins = abs($transactionOut->coins) * $this->systemTax;
        $expectedBalance = $transactionIn->coins - abs($transactionOut->coins) - $systemCoins;
        $expectedBalance = $expectedBalance * (1 - $this->systemTax);
        $this->assertEquals($expectedBalance, $this->response->getData()->userBalance);
    }

    /**
     * @depends testCreateIn
     * @depends testCreateOut
     * @return void
     */
    public function testBalanceAfterExpire(Transaction $transactionIn, Transaction $transactionOut)
    {
        // Vou simular que uma transaction expirou
        $beforeDate = $transactionOut->created_at;
        $transactionOut->update(['created_at' => '1900-10-10 10:00:00']);

        $this->testBalance($transactionIn, $transactionOut);

        // Volto a data ao normal
        $transactionOut->update(['created_at' => $beforeDate]);
    }

    /**
     * @depends testCreateOut
     * @return void
     */
    public function testConfirm($transactionOut)
    {
        $url = '/transaction/confirm/' . $transactionOut->id;
        $this->post($url, [], $this->headers)->seeJsonStructure([
            'status', 'message'
        ]);

        $this->response->assertStatus(200);
    }

    /**
     * @depends testCreateIn
     * @depends testCreateOut
     * @depends testConfirm
     * @return void
     */
    public function testBalanceAfterConfirm(Transaction $transactionIn, Transaction $transactionOut)
    {
        $this->testBalance($transactionIn, $transactionOut);
    }
}
