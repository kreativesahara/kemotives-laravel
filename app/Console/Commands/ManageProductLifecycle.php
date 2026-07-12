<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Car;
use Illuminate\Support\Facades\Log;

class ManageProductLifecycle extends Command
{
    protected $signature = 'products:manage-lifecycle';
    protected $description = 'Deactivates 7-day old listings and deletes listings at max cycle count';

    public function handle()
    {
        $this->info('Starting product lifecycle management...');

        try {
            $sevenDaysAgo = now()->subDays(7);

            $expiredListings = Car::where('is_active', 'true')
                ->where('created_at', '<', $sevenDaysAgo)
                ->get();

            $this->info("Found {$expiredListings->count()} expired listings to deactivate");

            foreach ($expiredListings as $listing) {
                $currentTime = now();
                $newCycleCount = $listing->cycle_count + 1;

                $history = $listing->cycle_count_history ?? [];
                if (!is_array($history)) {
                    $history = json_decode($history, true);
                    if (!is_array($history)) $history = [];
                }

                $history[] = $currentTime->toISOString();

                $listing->update([
                    'is_active' => 'false',
                    'status' => 'inactive',
                    'cycle_count' => $newCycleCount,
                    'cycle_count_history' => $history,
                ]);

                $this->info("Deactivated listing ID {$listing->id}, new cycle count: {$newCycleCount}");
            }

            $maxCycleListings = Car::where('cycle_count', '>=', 4)->get();
            $this->info("Found {$maxCycleListings->count()} listings at max cycle count to delete");

            foreach ($maxCycleListings as $listing) {
                $listing->delete();
                $this->info("Deleted listing ID {$listing->id} that reached max cycle count");
            }

            $this->info('Product lifecycle management completed successfully');
        } catch (\Exception $error) {
            Log::error('Error in product lifecycle management: ' . $error->getMessage());
        }
    }
}
