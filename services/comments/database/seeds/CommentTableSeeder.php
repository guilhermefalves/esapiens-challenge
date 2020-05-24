<?php

use Illuminate\Database\Seeder;
use App\Models\Comment;

class CommentTableSeeder extends Seeder
{
    public function run()
    {
        $numberOfPosts = 25;
        for ($i = 0; $i < $numberOfPosts; $i++) {
            $comment = factory(Comment::class)->make()->makeVisible(['post_id'])->toArray();
            Comment::create($comment);
        }
    }
}
