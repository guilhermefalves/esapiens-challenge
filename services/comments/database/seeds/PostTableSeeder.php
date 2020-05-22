<?php

use Illuminate\Database\Seeder;

use App\Models\Post;

class PostTableSeeder extends Seeder
{
    public function run()
    {
        $numberOfPosts = 10;
        for ($i = 0; $i < $numberOfPosts; $i++) {
            $post = factory(Post::class)->make()->toArray();
            Post::create($post);
        }
    }
}
