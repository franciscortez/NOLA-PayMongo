<?php

namespace Database\Factories;

use App\Models\LocationToken;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationTokenFactory extends Factory
{
    protected $model = LocationToken::class;

    public function definition(): array
    {
        return [
            'location_id' => 'loc_' . $this->faker->unique()->lexify('????????'),
            'location_name' => $this->faker->company,
            'access_token' => 'access_' . $this->faker->sha256(),
            'refresh_token' => 'refresh_' . $this->faker->sha256(),
            'expires_at' => now()->addHours(2),
            'user_type' => 'Location',
            'paymongo_test_secret_key' => null,
            'paymongo_test_publishable_key' => null,
            'paymongo_test_webhook_id' => null,
            'paymongo_test_webhook_secret' => null,
            'paymongo_live_secret_key' => null,
            'paymongo_live_publishable_key' => null,
            'paymongo_live_webhook_id' => null,
            'paymongo_live_webhook_secret' => null,
        ];
    }
}
