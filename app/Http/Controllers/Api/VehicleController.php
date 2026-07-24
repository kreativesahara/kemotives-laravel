<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\VehicleRepository;
use App\Services\VehicleService;
use App\Jobs\ProcessCloudinaryUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Car;
use Cloudinary\Cloudinary;

class VehicleController extends Controller
{
    protected $repository;
    protected $service;

    public function __construct(VehicleRepository $repository, VehicleService $service)
    {
        $this->repository = $repository;
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $limit = $request->input('limit', 100);
        $sortBy = $request->input('sortBy', 'createdAt');
        $order = $request->input('order', 'desc');

        $cars = $this->repository->getAllActive($limit, $sortBy, $order);

        if ($cars->isEmpty()) {
            return response()->json(['message' => 'No products found.'], 204);
        }

        // Return a flat array perfectly matching the Express JSON shape
        return response()->json($cars->map->formatForApi()->toArray());
    }

    public function show($slug)
    {
        $car = $this->repository->getBySlug($slug);

        if (!$car) {
            return response()->json(['message' => 'No product found.'], 204);
        }

        $formatted = $car->formatForApi();
        
        // Match the express shape which included productSeller
        // Express returned productSeller array
        $seller = $car->seller;
        $formatted['productSeller'] = $seller ? [[
            'id' => $seller->id,
            'userId' => $seller->user_id,
            'username' => $seller->username,
            'accountType' => $seller->account_type,
            'contact' => $seller->contact,
            'place' => $seller->place,
            'imageUrl' => $seller->image_url,
            'hasFinancing' => $seller->has_financing,
            'acceptsTradeIn' => $seller->accepts_trade_in,
            'hasSubscription' => $seller->has_subscription,
            'createdAt' => $seller->created_at,
        ]] : [];

        return response()->json($formatted);
    }

    public function trackView($slug)
    {
        $views = $this->repository->incrementViews($slug);

        if ($views === null) {
            return response()->noContent();
        }

        return response()->json([
            'message' => 'View tracked successfully.',
            'views' => $views
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validation
        $request->validate([
            'make' => 'required',
            'model' => 'required',
            'year' => 'required',
            'engineCapacity' => 'required',
            'fuelType' => 'required',
            'transmission' => 'required',
            'driveSystem' => 'required',
            'mileage' => 'required',
            'features' => 'required',
            'condition' => 'required',
            'location' => 'required',
            'price' => 'required',
            'sellerId' => 'required',
            'category' => 'required',
            'images' => 'required|array|min:5|max:10',
            'images.*' => 'required|file|mimes:jpeg,png,jpg,webp|max:5120'
        ]);

        // 2. Ensure image files are actually present (guard against empty array)
        if (!$request->hasFile('images') || count($request->file('images')) < 5) {
            return response()->json([
                'message' => 'At least 5 image files are required.',
                'errors' => ['images' => ['At least 5 image files are required.']]
            ], 422);
        }

        // 3. Check limits
        $limitCheck = $this->service->checkSellerListingLimit($request->sellerId);

        if (!$limitCheck['canCreateMore']) {
            return response()->json([
                'message' => "Listing limit reached for {$limitCheck['planName']} plan. Maximum {$limitCheck['maxListings']} listings allowed. Current listings: {$limitCheck['currentListingsCount']}",
                'limitDetails' => $limitCheck
            ], 403);
        }

        // 4. Generate Slug
        $slug = $this->service->generateSlug(
            $request->make, $request->model, $request->year, $request->location
        );

        // 5. Store temp files first (before DB insert) to fail fast on storage issues
        $localPaths = [];
        foreach ($request->file('images') as $file) {
            $path = $file->store('temp_uploads', 'local');
            if (!$path) {
                // Clean up any already-stored temp files
                foreach ($localPaths as $storedPath) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($storedPath);
                }
                return response()->json([
                    'message' => 'Failed to process uploaded images.',
                    'errors' => ['images' => ['Failed to store one or more image files.']]
                ], 422);
            }
            $localPaths[] = $path;
        }

        // 6. Create Product (only after images are confirmed stored)
        $car = $this->repository->create([
            'seller_id' => $request->sellerId,
            'make' => $request->make,
            'model' => $request->model,
            'yom' => $request->year,
            'slug' => $slug,
            'engine_capacity' => $request->engineCapacity,
            'fuel_type' => $request->fuelType,
            'transmission' => $request->transmission,
            'drive_system' => $request->driveSystem,
            'mileage' => $request->mileage,
            'features' => $request->features,
            'car_condition' => $request->condition,
            'vehicle_registration_prefix' => $request->vehicleRegPrefix,
            'vehicle_registration_suffix' => $request->vehicleRegSuffix,
            'view_location' => $request->location,
            'price' => $request->price,
            'category' => $request->category,
            'ai_grade' => $request->aiGrade,
            'ai_score' => $request->aiScore,
            'ai_notes' => $request->aiNotes,
            'is_active' => "true",
            'status' => "active",
            'cycle_count' => 0,
            'cycle_count_history' => "[]",
            'views' => 0,
        ]);

        // 7. Dispatch Cloudinary upload job
        ProcessCloudinaryUpload::dispatch($car->id, $localPaths);

        // 8. Return response
        return response()->json([
            'message' => 'Product created successfully',
            'carDetails' => $car->formatForApi(),
            'listingLimitDetails' => $limitCheck
        ], 201);
    }

    public function update(Request $request)
    {
        $productId = $request->input('id');
        if (!$productId) {
            return response()->json(['message' => 'Product ID is required.'], 400);
        }

        $car = $this->repository->findById($productId);
        if (!$car) {
            return response()->json(['message' => "No product found with ID {$productId}."], 404);
        }

        $updateData = [];

        // Map camelCase JSON payload to snake_case DB columns
        $mapping = [
            'make' => 'make', 'model' => 'model', 'year' => 'yom',
            'engineCapacity' => 'engine_capacity', 'fuelType' => 'fuel_type',
            'transmission' => 'transmission', 'driveSystem' => 'drive_system',
            'mileage' => 'mileage', 'features' => 'features',
            'condition' => 'car_condition', 'vehicleRegPrefix' => 'vehicle_registration_prefix',
            'vehicleRegSuffix' => 'vehicle_registration_suffix', 'location' => 'view_location',
            'price' => 'price', 'sellerId' => 'seller_id', 'aiGrade' => 'ai_grade',
            'aiScore' => 'ai_score', 'aiNotes' => 'ai_notes'
        ];

        foreach ($mapping as $requestKey => $dbColumn) {
            if ($request->has($requestKey)) {
                $updateData[$dbColumn] = $request->input($requestKey);
            }
        }

        if ($request->has('isActive')) {
            $isActive = $request->input('isActive');
            if ($isActive === "true" && $car->getRawOriginal('is_active') === "false" && $car->cycle_count < 4) {
                $updateData['is_active'] = "true";
                $updateData['status'] = "active";
            } elseif ($isActive === "false") {
                $updateData['is_active'] = "false";
                $updateData['status'] = "inactive";
            }
        }

        if ($request->has('make') || $request->has('model')) {
            $newMake = $request->input('make', $car->make);
            $newModel = $request->input('model', $car->model);
            $newYear = $request->input('year', $car->yom);
            $newLocation = $request->input('location', $car->view_location);
            $updateData['slug'] = $this->service->generateSlug($newMake, $newModel, $newYear, $newLocation);
        }

        $this->repository->update($productId, $updateData);

        return response()->json([
            'message' => "Product updated successfully",
            'updatedFields' => $updateData
        ]);
    }

    public function toggleActiveStatus(Request $request, $id)
    {
        $isActive = $request->input('isActive');

        if ($isActive !== "true" && $isActive !== "false") {
            return response()->json(['message' => 'Valid isActive value is required ("true" or "false").'], 400);
        }

        $car = $this->repository->findById($id);

        if (!$car) {
            return response()->json(['message' => "No product found with ID {$id}."], 404);
        }

        if ($isActive === "true" && $car->cycle_count >= 4) {
            return response()->json([
                'message' => "Cannot reactivate product. Maximum cycle count reached."
            ], 400);
        }

        $status = $isActive === "true" ? "active" : "inactive";

        $this->repository->update($id, [
            'is_active' => $isActive,
            'status' => $status
        ]);

        return response()->json([
            'message' => "Product status updated to {$status}."
        ]);
    }

    public function destroy($id)
    {
        try {
            $car = Car::with('images')->find($id);
            if (!$car) {
                return response()->json(['message' => 'Product not found.'], 404);
            }

            try {
                $cloudinaryUrl = env('CLOUDINARY_URL') ?? "cloudinary://" . env('CLOUDINARY_API_KEY') . ":" . env('CLOUDINARY_API_SECRET') . "@" . env('CLOUDINARY_CLOUD_NAME');
                if (env('CLOUDINARY_URL') || (env('CLOUDINARY_API_KEY') && env('CLOUDINARY_API_SECRET') && env('CLOUDINARY_CLOUD_NAME'))) {
                    $cloudinary = new Cloudinary($cloudinaryUrl);

                    foreach ($car->images as $image) {
                        if ($image->image_url) {
                            $parts = explode('/', $image->image_url);
                            $filename = end($parts);
                            $publicId = "diksx/cars/{$id}/" . explode('.', $filename)[0];
                            try {
                                $cloudinary->uploadApi()->destroy($publicId);
                            } catch (\Exception $e) {
                                Log::warning("Failed to delete Cloudinary image: " . $e->getMessage());
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Cloudinary cleanup error for product {$id}: " . $e->getMessage());
            }

            // Explicitly delete associated car_images records first to prevent foreign key constraint violations
            $car->images()->delete();

            // Delete product
            $car->delete();

            return response()->json([
                'message' => 'Product and associated images deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error deleting product {$id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sellerProducts(Request $request, $sellerId)
    {
        $authenticatedUserId = $request->user()->id;

        if ($authenticatedUserId != $sellerId) {
            return response()->json(['message' => 'Access denied. You can only view your own products.'], 403);
        }

        $limit = $request->input('limit', 100);
        $sortBy = $request->input('sortBy', 'createdAt');
        $order = $request->input('order', 'desc');

        $cars = $this->repository->getSellerProducts($sellerId, $limit, $sortBy, $order);

        if ($cars->isEmpty()) {
            return response()->json(['message' => 'No products found for this seller.'], 204);
        }

        return response()->json($cars->map->formatForApi()->toArray());
    }

    public function limitStatus($sellerId)
    {
        $limitDetails = $this->service->checkSellerListingLimit($sellerId);
        return response()->json([
            'message' => 'Listing limit status retrieved successfully',
            'limitDetails' => $limitDetails
        ]);
    }
}
