<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Visits extends Model
{
    use HasFactory;

    protected $table = 'visits';

    protected $fillable = [
        'unique_id',
        'ip_address',
        'country',
        'country_code',
        'isp',
        'language',
        'user_agent',
        'type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
