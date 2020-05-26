<?php

use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds only to Posts.
     *
     * @return void
     */
    public function run()
    {
        $this->call('PostTableSeeder');
    }
}
