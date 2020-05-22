<?php

namespace Tests;

use App\Models\Comment;
use App\Models\Post;

class CommentControllerTest extends TestCase
{
    public function testAB()
    {
        $post = factory(Post::class)->make()->toArray();
        $comment = factory(Comment::class)->make()->toArray();

        print_r($post);
        echo "\n\n\n\n\n\n";
        print_r($comment);

        $this->assertTrue(true);
    }

}