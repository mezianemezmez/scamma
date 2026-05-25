<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomForm extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'fields',
        'settings',
        'is_active',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fields' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the custom form.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
