<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\LocationToken;
use App\Services\GhlService;
use Mockery;
use Mockery\MockInterface;

class CheckGhlTokenTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_does_not_refresh_valid_token()
    {
        $token = LocationToken::factory()->create([
            'access_token' => 'valid_access',
            'refresh_token' => 'valid_refresh',
            'expires_at' => now()->addHours(1),
        ]);

        $this->mock(GhlService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('refreshToken');
        });

        $response = $this->get('/provider/config?location_id=' . $token->location_id);

        $response->assertStatus(200);
    }

    public function test_it_refreshes_expired_token()
    {
        $token = LocationToken::factory()->create([
            'access_token' => 'expired_access',
            'refresh_token' => 'valid_refresh',
            'expires_at' => now()->subMinutes(10),
        ]);

        $this->mock(GhlService::class, function (MockInterface $mock) use ($token) {
            $mock->shouldReceive('refreshToken')
                ->once()
                ->with(Mockery::on(function ($arg) use ($token) {
                    return $arg->id === $token->id;
                }))
                ->andReturn([
                    'access_token' => 'new_access',
                    'refresh_token' => 'new_refresh',
                    'expires_in' => 86400,
                ]);
        });

        $response = $this->get('/provider/config?location_id=' . $token->location_id);

        $response->assertStatus(200);
    }

    public function test_it_refreshes_soon_to_expire_token()
    {
        $token = LocationToken::factory()->create([
            'access_token' => 'expiring_access',
            'refresh_token' => 'valid_refresh',
            'expires_at' => now()->addMinutes(2), // Less than 5 mins limit
        ]);

        $this->mock(GhlService::class, function (MockInterface $mock) use ($token) {
            $mock->shouldReceive('refreshToken')
                ->once()
                ->with(Mockery::on(function ($arg) use ($token) {
                    return $arg->id === $token->id;
                }));
        });

        $response = $this->get('/provider/config?location_id=' . $token->location_id);

        $response->assertStatus(200);
    }
}
