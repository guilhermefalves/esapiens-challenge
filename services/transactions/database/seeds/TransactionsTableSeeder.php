<?php

use Illuminate\Database\Seeder;
use App\Models\Transaction;

class TransactionsTableSeeder extends Seeder
{
    public function run()
    {
        for ($i = 0; $i < 10; $i++) {
            $transaction = factory(Transaction::class)
                ->make()
                ->toArray();
            Transaction::create($transaction);
        }
    }
}
