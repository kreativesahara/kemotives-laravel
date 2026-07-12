<?php

namespace App\Jobs;

use App\Models\CarImage;
use Cloudinary\Cloudinary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessCloudinaryUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $carId;
    protected array $localFilePaths;

    public function __construct(int $carId, array $localFilePaths)
    {
        $this->carId = $carId;
        $this->localFilePaths = $localFilePaths;
    }

    public function handle()
    {
        $cloudinaryUrl = env('CLOUDINARY_URL');
        if (!$cloudinaryUrl) {
            // Reconstruct if not using URL format
            $cloudName = env('CLOUDINARY_CLOUD_NAME');
            $apiKey = env('CLOUDINARY_API_KEY');
            $apiSecret = env('CLOUDINARY_API_SECRET');
            $cloudinaryUrl = "cloudinary://{$apiKey}:{$apiSecret}@{$cloudName}";
        }

        $cloudinary = new Cloudinary(['cloudinary_url' => $cloudinaryUrl]);

        foreach ($this->localFilePaths as $localPath) {
            $absolutePath = Storage::disk('local')->path($localPath);
            
            if (file_exists($absolutePath)) {
                $uploadResult = $cloudinary->uploadApi()->upload($absolutePath, [
                    'folder' => "diksx/cars/{$this->carId}",
                    'resource_type' => 'auto',
                    'transformation' => [
                        ['width' => 1000, 'height' => 750, 'crop' => 'fill'],
                        ['quality' => 'auto']
                    ]
                ]);

                if (isset($uploadResult['secure_url'])) {
                    CarImage::create([
                        'car_id' => $this->carId,
                        'image_url' => $uploadResult['secure_url']
                    ]);
                }

                // Clean up local temp file
                Storage::disk('local')->delete($localPath);
            }
        }
    }
}
