<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationToken extends Model
{
    protected $fillable = [
        'location_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'user_type'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];
}
