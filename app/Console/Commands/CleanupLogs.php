<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove webhook logs older than 10 days using model pruning';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting log cleanup...');
        
        $this->call('model:prune', [
            '--model' => [\App\Models\WebhookLog::class],
        ]);

        $this->info('Log cleanup completed.');
    }
}
