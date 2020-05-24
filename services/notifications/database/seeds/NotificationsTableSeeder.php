<?php

use App\Models\Notification;
use Illuminate\Database\Seeder;
use App\Models\User;

class NotificationsTableSeeder extends Seeder
{
    public function run()
    {
        for ($i = 0; $i < 50; $i++) {
            $user = factory(Notification::class)
                ->make()
                ->makeVisible(['content'])
                ->toArray();
            Notification::create($user);
        }
    }
}
