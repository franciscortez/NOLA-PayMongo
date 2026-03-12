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
        Schema::create('location_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('location_id')->unique();
            $table->string('location_name')->nullable();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('expires_at');
            $table->string('user_type')->nullable();
            // PayMongo — Test mode
            $table->text('paymongo_test_secret_key')->nullable();
            $table->text('paymongo_test_publishable_key')->nullable();
            $table->string('paymongo_test_webhook_id')->nullable();
            $table->text('paymongo_test_webhook_secret')->nullable();
            // PayMongo — Live mode
            $table->text('paymongo_live_secret_key')->nullable();
            $table->text('paymongo_live_publishable_key')->nullable();
            $table->string('paymongo_live_webhook_id')->nullable();
            $table->text('paymongo_live_webhook_secret')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_tokens');
    }
};
