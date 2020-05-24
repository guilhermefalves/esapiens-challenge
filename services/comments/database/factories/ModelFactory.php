<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Post;
use App\Models\Comment;
use Faker\Generator as Faker;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
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

$factory->define(Post::class, function (Faker $faker) {
    return [
        'user_id' => $faker->numberBetween(1, 5),
        'title'   => $faker->sentence(8),
        'content' => $faker->sentence(20),
        'type'    => Arr::random(['text', 'photo', 'movie'], 1)[0]
    ];
});

$factory->define(Comment::class, function (Faker $faker) {
    $coins        = $faker->boolean ? $faker->numberBetween(10, 300) : 0;
    $highlight_up = Carbon::now()->addMinutes($coins)->format('Y-m-d H:i:s');

    return [
        'user_id'      => $faker->numberBetween(1, 5),
        'post_id'      => Post::inRandomOrder()->get('id')->first()->id,
        'title'        => $faker->sentence(8),
        'content'      => $faker->sentence(50),
        'coins'        => $coins,
        'highlight_up' => $coins ? $highlight_up : null
    ];
});
