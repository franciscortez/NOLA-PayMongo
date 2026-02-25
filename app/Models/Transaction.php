<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
   protected $fillable = [
      'checkout_session_id',
      'payment_intent_id',
      'payment_id',
      'ghl_transaction_id',
      'ghl_order_id',
      'ghl_location_id',
      'amount',
      'amount_refunded',
      'currency',
      'description',
      'status',
      'payment_method',
      'customer_name',
      'customer_email',
      'metadata',
      'paid_at',
      'is_live_mode',
   ];

   protected $casts = [
      'metadata' => 'array',
      'paid_at' => 'datetime',
      'amount' => 'integer',
      'is_live_mode' => 'boolean',
   ];

   // ===== Scopes =====

   public function scopePaid($query)
   {
      return $query->where('status', 'paid');
   }

   public function scopePending($query)
   {
      return $query->where('status', 'pending');
   }

   public function scopeFailed($query)
   {
      return $query->where('status', 'failed');
   }

   public function scopeByLocation($query, string $locationId)
   {
      return $query->where('ghl_location_id', $locationId);
   }

   public function scopeByPaymentMethod($query, string $method)
   {
      return $query->where('payment_method', $method);
   }

   // ===== Helpers =====

   public function isPaid(): bool
   {
      return $this->status === 'paid';
   }

   public function isPending(): bool
   {
      return $this->status === 'pending';
   }

   /**
    * Format amount from cents to decimal.
    */
   public function getFormattedAmountAttribute(): string
   {
      $symbol = $this->currency === 'PHP' ? '₱' : $this->currency . ' ';
      return $symbol . number_format($this->amount / 100, 2);
   }
}
