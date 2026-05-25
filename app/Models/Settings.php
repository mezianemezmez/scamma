<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $fillable = [
        'bot_token',
        'chat_id',
        'chat_id_info',
        'page_login',
        'page_info',
        'price',
        'tracking',
        'panel',
        'panel_telegram',
    ];
}
