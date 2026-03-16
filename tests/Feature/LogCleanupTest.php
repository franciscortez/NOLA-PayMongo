<?php

namespace Tests\Feature;

use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class LogCleanupTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_deletes_logs_older_than_10_days()
    {
        // 1. Create a log from 11 days ago (should be pruned)
        $oldLog = WebhookLog::create([
            'event_id' => 'evt_old',
            'event_type' => 'test.event',
            'payload' => ['foo' => 'bar'],
            'status' => 'processed',
        ]);
        $oldLog->created_at = Carbon::now()->subDays(11);
        $oldLog->save();

        // 2. Create a log from 9 days ago (should NOT be pruned)
        $recentLog = WebhookLog::create([
            'event_id' => 'evt_recent',
            'event_type' => 'test.event',
            'payload' => ['foo' => 'bar'],
            'status' => 'processed',
        ]);
        $recentLog->created_at = Carbon::now()->subDays(9);
        $recentLog->save();

        // 3. Create a log from today (should NOT be pruned)
        $todayLog = WebhookLog::create([
            'event_id' => 'evt_today',
            'event_type' => 'test.event',
            'payload' => ['foo' => 'bar'],
            'status' => 'processed',
        ]);

        // Verify they all exist first
        $this->assertDatabaseCount('webhook_logs', 3);

        // 4. Run the cleanup command
        $this->artisan('logs:cleanup')
            ->expectsOutput('Starting log cleanup...')
            ->expectsOutput('Log cleanup completed.')
            ->assertExitCode(0);

        // 5. Assertions
        $this->assertDatabaseMissing('webhook_logs', ['event_id' => 'evt_old']);
        $this->assertDatabaseHas('webhook_logs', ['event_id' => 'evt_recent']);
        $this->assertDatabaseHas('webhook_logs', ['event_id' => 'evt_today']);
        $this->assertDatabaseCount('webhook_logs', 2);
    }
}
