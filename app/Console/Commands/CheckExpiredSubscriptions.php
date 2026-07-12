<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Models\Seller;
use Illuminate\Support\Facades\Log;

class CheckExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:check-expired';
    protected $description = 'Check expired subscriptions and update their status';

    public function handle()
    {
        $this->info('Running check for expired subscriptions...');
        Log::info('[SUBSCRIPTION] Running check for expired subscriptions at ' . now()->toISOString());

        $now = now();

        $activeSubs = Subscription::where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', $now)
            ->get();

        $this->info("Found {$activeSubs->count()} expired subscriptions to check");

        $updatedCount = 0;

        foreach ($activeSubs as $sub) {
            try {
                $sub->update([
                    'status' => 'expired'
                ]);

                Seller::where('user_id', $sub->user_id)->update(['has_subscription' => false]);

                $updatedCount++;
                Log::info("[SUBSCRIPTION] Subscription {$sub->id} for user {$sub->user_id} has expired and updated");
            } catch (\Exception $error) {
                Log::error("[SUBSCRIPTION ERROR] Failed to update expired subscription {$sub->id}: " . $error->getMessage());
            }
        }

        $this->info("Updated {$updatedCount} expired subscriptions");
    }
}
