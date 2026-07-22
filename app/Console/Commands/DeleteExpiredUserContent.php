<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DeletedSeller;
use App\Models\Car;
use App\Models\CarImage;
use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Log;

class DeleteExpiredUserContent extends Command
{
    protected $signature = 'users:delete-expired-content';
    protected $description = 'Delete content for sellers who deleted their accounts 90 days ago';

    public function handle()
    {
        $this->info('Running expired user content cleanup...');

        $ninetyDaysAgo = now()->subDays(90);

        $deletionCandidates = DeletedSeller::where('deleted_at', '<', $ninetyDaysAgo)->get();

        if ($deletionCandidates->isEmpty()) {
            $this->info('No expired user content to delete');
            return;
        }

        $this->info("Found {$deletionCandidates->count()} users with expired content to delete");

        $cloudinaryUrl = env('CLOUDINARY_URL') ?? "cloudinary://" . env('CLOUDINARY_API_KEY') . ":" . env('CLOUDINARY_API_SECRET') . "@" . env('CLOUDINARY_CLOUD_NAME');
        $cloudinary = new Cloudinary($cloudinaryUrl);

        foreach ($deletionCandidates as $deletedUser) {
            $userId = $deletedUser->user_id;

            try {
                $sellerProducts = Car::where('seller_id', $userId)->get();

                foreach ($sellerProducts as $prod) {
                    try {
                        $cloudinary->adminApi()->deleteAssetsByPrefix("diksx/cars/{$userId}/{$prod->slug}");
                        Log::info("Deleted Cloudinary images for product {$prod->id}");
                    } catch (\Exception $cloudinaryError) {
                        Log::error("Error deleting Cloudinary images for product {$prod->id}: " . $cloudinaryError->getMessage());
                    }
                }

                CarImage::whereIn('car_id', $sellerProducts->pluck('id'))->delete();
                Car::where('seller_id', $userId)->delete();
                Log::info("Deleted {$sellerProducts->count()} products for user ID: {$userId}");

                try {
                    $cloudinary->adminApi()->deleteAssetsByPrefix("diksx/sellers/{$userId}");
                    $cloudinary->adminApi()->deleteFolder("diksx/sellers/{$userId}");
                    Log::info("Deleted Cloudinary folder for seller with user ID: {$userId}");
                } catch (\Exception $cloudinaryError) {
                    Log::error("Error deleting Cloudinary folder for seller {$userId}: " . $cloudinaryError->getMessage());
                }

                $deletedUser->delete();

                $this->info("Successfully deleted all content for user ID: {$userId}");
            } catch (\Exception $userError) {
                Log::error("Error processing deletion for user ID {$userId}: " . $userError->getMessage());
            }
        }
    }
}
