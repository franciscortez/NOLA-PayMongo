<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnsureHttpsMiddlewareTest extends TestCase
{
    public function test_does_not_redirect_to_https_in_testing_environment()
    {
        $this->assertSame('testing', app()->environment());

        $response = $this->get('/api/health');

        $response->assertStatus(200);
    }

    public function test_health_endpoint_responds()
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure(['status']);
    }
}
