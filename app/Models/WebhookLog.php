<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class WebhookLog extends Model
{
    use Prunable;

    protected $fillable = [
        'event_id',
        'event_type',
        'payload',
        'status',
        'error_message',
    ];

    /**
     * Get the prunable model query.
     */
    public function prunable()
    {
        return static::where('created_at', '<=', now()->subDays(10));
    }

    protected $casts = [
        'payload' => 'array',
    ];
}
