<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Transaction;
use Faker\Generator as Faker;
use Illuminate\Support\Arr;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(Transaction::class, function (Faker $faker) {
    $type = Arr::random(['in', 'out'], 1)[0];
    $systemTransaction = ($type == 'in') ? false : $faker->boolean();
    $transactionID = ($systemTransaction)
        ? Transaction::inRandomOrder()->where('system_transaction', false)->get('id')->first()
        : null;
    $coins = $faker->randomNumber(3);
    return [
        'user_id'        => $faker->numberBetween(1, 5),
        'comment_id'     => $faker->numberBetween(1, 20),
        'transaction_id' => $transactionID,

        'coins' => $type == 'in' ? $coins : -$coins,
        'type'  => $type,
        'tax'   => config('app.systemTax'),

        'system_transaction' => $systemTransaction
    ];
});
