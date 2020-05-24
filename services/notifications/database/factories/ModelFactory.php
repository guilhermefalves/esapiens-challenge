<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Notification;
use Faker\Generator as Faker;

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

$factory->define(Notification::class, function (Faker $faker) {
    $sended_at = $faker->boolean()
        ? $faker->dateTimeThisMonth()->format('Y-m-d H:i:s')
        : null;
    $readed_at = $sended_at && $faker->boolean() 
        ? $faker->dateTimeBetween($sended_at)->format('Y-m-d H:i:s')
        : null;
    
    return [
        'to'         => $faker->numberBetween(1, 5),
        'mail_to'    => $faker->email,
        'from'       => $faker->numberBetween(1, 5),
        'content'    => $faker->sentence(20),
        'sended'     => (bool) $sended_at,
        'sended_at'  => $sended_at,
        'readed'     => (bool) $readed_at,
        'readed_at'  => $readed_at
    ];
});

