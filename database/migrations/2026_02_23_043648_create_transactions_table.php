<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // PayMongo references
            $table->string('checkout_session_id')->unique()->nullable();
            $table->string('payment_intent_id')->nullable()->index();
            $table->string('payment_id')->nullable();

            // GHL references
            $table->string('ghl_transaction_id')->nullable()->index();
            $table->string('ghl_order_id')->nullable();
            $table->string('ghl_location_id')->nullable()->index();

            // Payment details
            $table->integer('amount');                          // in cents
            $table->integer('amount_refunded')->default(0);     // in cents
            $table->string('currency', 3)->default('PHP');
            $table->string('description')->nullable();
            $table->string('status')->default('pending');       // pending, paid, failed, refunded, expired
            $table->boolean('is_live_mode')->default(false);
            $table->string('payment_method')->nullable();       // card, qrph, gcash, grab_pay, paymaya

            // Customer
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();

            // Raw data
            $table->json('metadata')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
