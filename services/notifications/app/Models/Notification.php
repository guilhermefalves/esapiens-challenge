<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'content',
        'to', 'mail_to', 'from',
        'sended', 'sended_at', 'readed', 'readed_at'
    ];

    protected $hidden = [
        'content'
    ];
}
