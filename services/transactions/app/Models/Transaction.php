<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Controller das transactions
 * 
 * @author Guilherme Alves <guihalves20@gmail.com>
 */
class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'comment_id', 'transaction_id',
        'coins', 'type', 'confirmed',
        'system_transaction', 'tax'
    ];
}
