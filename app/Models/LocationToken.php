<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'location_name',
        'access_token',
        'refresh_token',
        'expires_at',
        'user_type',
        // PayMongo — Test
        'paymongo_test_secret_key',
        'paymongo_test_publishable_key',
        'paymongo_test_webhook_id',
        'paymongo_test_webhook_secret',
        // PayMongo — Live
        'paymongo_live_secret_key',
        'paymongo_live_publishable_key',
        'paymongo_live_webhook_id',
        'paymongo_live_webhook_secret',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'paymongo_test_secret_key' => 'encrypted',
        'paymongo_test_publishable_key' => 'encrypted',
        'paymongo_test_webhook_secret' => 'encrypted',
        'paymongo_live_secret_key' => 'encrypted',
        'paymongo_live_publishable_key' => 'encrypted',
        'paymongo_live_webhook_secret' => 'encrypted',
    ];
}
