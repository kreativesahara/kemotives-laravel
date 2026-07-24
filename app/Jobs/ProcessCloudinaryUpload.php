<?php

namespace App\Jobs;

use App\Models\CarImage;
use Cloudinary\Cloudinary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessCloudinaryUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Backoff between retries (seconds): 10s, 30s, 60s
     */
    public array $backoff = [10, 30, 60];

    protected int $carId;
    protected array $localFilePaths;

    public function __construct(int $carId, array $localFilePaths)
    {
        $this->carId = $carId;
        $this->localFilePaths = $localFilePaths;
    }

    public function handle()
    {
        // If the car was deleted before the job was processed, just clean up temp files and exit
        $carExists = \App\Models\Car::where('id', $this->carId)->exists();

        if (!$carExists) {
            $this->cleanupTempFiles();
            Log::info("ProcessCloudinaryUpload: Car {$this->carId} no longer exists. Cleaned up temp files.");
            return;
        }

        $cloudinaryUrl = env('CLOUDINARY_URL');
        if (!$cloudinaryUrl) {
            // Reconstruct if not using URL format
            $cloudName = env('CLOUDINARY_CLOUD_NAME');
            $apiKey = env('CLOUDINARY_API_KEY');
            $apiSecret = env('CLOUDINARY_API_SECRET');
            $cloudinaryUrl = "cloudinary://{$apiKey}:{$apiSecret}@{$cloudName}";
        }

        $cloudinary = new Cloudinary($cloudinaryUrl);
        $uploadedCount = 0;

        foreach ($this->localFilePaths as $localPath) {
            $absolutePath = Storage::disk('local')->path($localPath);

            if (!file_exists($absolutePath)) {
                Log::warning("ProcessCloudinaryUpload: Temp file not found: {$localPath}");
                continue;
            }

            // Upload with retry for transient network errors (cURL error 6, etc.)
            $uploadResult = $this->uploadWithRetry($cloudinary, $absolutePath, 3);

            if ($uploadResult && isset($uploadResult['secure_url'])) {
                CarImage::create([
                    'car_id' => $this->carId,
                    'image_url' => $uploadResult['secure_url']
                ]);
                $uploadedCount++;
            } else {
                Log::error("ProcessCloudinaryUpload: Failed to upload {$localPath} for car {$this->carId} after retries.");
            }

            // Clean up local temp file regardless of upload outcome
            Storage::disk('local')->delete($localPath);
        }

        Log::info("ProcessCloudinaryUpload: Completed for car {$this->carId}. Uploaded {$uploadedCount}/" . count($this->localFilePaths) . " images.");
    }

    /**
     * Attempt a single Cloudinary upload with per-file retries and exponential backoff.
     * This handles transient cURL/DNS errors (error 6) that are common on shared hosting.
     */
    private function uploadWithRetry(Cloudinary $cloudinary, string $absolutePath, int $maxAttempts): ?array
    {
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                return $cloudinary->uploadApi()->upload($absolutePath, [
                    'folder' => "diksx/cars/{$this->carId}",
                    'resource_type' => 'auto',
                    'transformation' => [
                        ['width' => 1000, 'height' => 750, 'crop' => 'fill'],
                        ['quality' => 'auto']
                    ]
                ]);
            } catch (\GuzzleHttp\Exception\ConnectException $e) {
                $attempt++;
                Log::warning("ProcessCloudinaryUpload: Connection error (attempt {$attempt}/{$maxAttempts}) for car {$this->carId}: " . $e->getMessage());

                if ($attempt < $maxAttempts) {
                    // Exponential backoff: 2s, 4s, 8s...
                    sleep(pow(2, $attempt));
                }
            } catch (\Exception $e) {
                // Non-transient error — don't retry
                Log::error("ProcessCloudinaryUpload: Upload error for car {$this->carId}: " . $e->getMessage());
                return null;
            }
        }

        return null;
    }

    /**
     * Clean up all temp files associated with this job.
     */
    private function cleanupTempFiles(): void
    {
        foreach ($this->localFilePaths as $localPath) {
            Storage::disk('local')->delete($localPath);
        }
    }

    /**
     * Handle a job failure — clean up temp files and delete the orphan car if no images were saved.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessCloudinaryUpload: Job failed permanently for car {$this->carId}: " . $exception->getMessage());

        $this->cleanupTempFiles();

        // If no images were saved, delete the orphan car record
        $imageCount = CarImage::where('car_id', $this->carId)->count();
        if ($imageCount === 0) {
            \App\Models\Car::where('id', $this->carId)->delete();
            Log::warning("ProcessCloudinaryUpload: Deleted orphan car {$this->carId} (no images uploaded).");
        }
    }
}
