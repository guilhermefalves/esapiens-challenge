<?php

use Illuminate\Database\Seeder;
use App\Models\User;

class UsersTableSeeder extends Seeder
{
    public function run()
    {
        for ($i = 0; $i < 5; $i++) {
            $user = factory(User::class)
                ->make()
                ->makeVisible(['password'])
                ->toArray();
            User::create($user);
        }
    }
}
