<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'post_id', 'title', 'content', 'coins', 'highlight_up', 'created_at'
    ];

    protected $hidden = ['deleted_at'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
