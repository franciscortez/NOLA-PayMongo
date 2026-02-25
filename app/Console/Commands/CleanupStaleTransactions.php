<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class CleanupStaleTransactions extends Command
{
   /**
    * The name and signature of the console command.
    *
    * @var string
    */
   protected $signature = 'transactions:cleanup-stale';

   /**
    * The console command description.
    *
    * @var string
    */
   protected $description = 'Mark pending transactions older than 24 hours as expired';

   /**
    * Execute the console command.
    */
   public function handle()
   {
      $this->info('Starting stale transactions cleanup...');
      Log::info('CleanupStaleTransactions: Started');

      $cutoffTime = now()->subHours(24);

      $staleTransactionsCount = Transaction::where('status', 'pending')
         ->where('created_at', '<', $cutoffTime)
         ->update(['status' => 'expired']);

      $message = "Finished cleaning up stale transactions. Marked {$staleTransactionsCount} transactions as expired.";

      $this->info($message);
      Log::info("CleanupStaleTransactions: {$message}");

      return Command::SUCCESS;
   }
}
