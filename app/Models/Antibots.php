<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Antibots extends Model
{
    protected $fillable = [
        'allowed_countries',
        'allowed_operators',
        'blocker_operators',
        'proxy_protection',
        'antibots_protection',
        'captcha_protection',
    ];

    protected $casts = [
        'allowed_countries' => 'array',
        'allowed_operators' => 'array',
        'blocker_operators' => 'array',
    ];
}
