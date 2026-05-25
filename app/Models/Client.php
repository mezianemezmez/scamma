<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'unique_id',
        'ip',
        'action',
        'last_page',
        'language',
        'ban',
        'country_code',
        'isp',
        'why',
    ];
}
