<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accessory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Cloudinary\Cloudinary;

class AccessoriesController extends Controller
{
    private function getCloudinary()
    {
        $cloudinaryUrl = env('CLOUDINARY_URL') ?? "cloudinary://" . env('CLOUDINARY_API_KEY') . ":" . env('CLOUDINARY_API_SECRET') . "@" . env('CLOUDINARY_CLOUD_NAME');
        return new Cloudinary($cloudinaryUrl);
    }

    private function generateAccessorySlug($name, $condition, $id = null)
    {
        $base = Str::slug($name . ' ' . $condition);
        if ($id) {
            return $base . '-' . $id;
        }
        return $base . '-' . Str::random(6);
    }

    private function extractPublicId($url)
    {
        if (preg_match('/\/v\d+\/(.+)\.\w+$/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function getAllAccessories(Request $request)
    {
        try {
            $limit = min((int) $request->query('limit', 100), 500);
            $sortBy = $request->query('sortBy', 'createdAt');
            $order = strtolower($request->query('order', 'desc')) === 'asc' ? 'asc' : 'desc';

            $validSortColumns = ['createdAt' => 'created_at', 'id' => 'id', 'price' => 'price', 'stock' => 'stock'];
            $sortColumn = $validSortColumns[$sortBy] ?? 'created_at';

            $accessories = Accessory::orderBy($sortColumn, $order)
                ->limit($limit)
                ->get();

            if ($accessories->isEmpty()) {
                return response()->json(['message' => 'No accessories found.'], 204);
            }

            return response()->json($accessories);
        } catch (\Exception $e) {
            Log::error('Error fetching accessories: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function getAccessoryById($slug)
    {
        try {
            $accessory = Accessory::where('slug', $slug)->first();

            if (!$accessory) {
                return response()->json(['message' => 'No accessory found.'], 204);
            }

            return response()->json($accessory);
        } catch (\Exception $e) {
            Log::error('Error fetching accessory: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function trackAccessoryView($slug)
    {
        try {
            $accessory = Accessory::where('slug', $slug)->first();

            if (!$accessory) {
                return response()->json(['message' => 'No accessory found.'], 204);
            }

            $accessory->increment('views');

            return response()->json([
                'message' => 'View tracked successfully.',
                'views' => $accessory->views
            ]);
        } catch (\Exception $e) {
            Log::error('Error tracking view for accessory: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function createAccessory(Request $request)
    {
        $name = $request->input('name');
        $price = $request->input('price');

        if (!$name || !$price) {
            return response()->json(['message' => 'Name and price are required.'], 400);
        }

        try {
            $imageUrls = [];

            if ($request->hasFile('images')) {
                $cloudinary = $this->getCloudinary();
                foreach ($request->file('images') as $file) {
                    $result = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                        'folder' => 'diksx/accessories',
                        'transformation' => [
                            ['width' => 1000, 'height' => 750, 'crop' => 'fill'],
                            ['quality' => 'auto']
                        ]
                    ]);
                    $imageUrls[] = $result['secure_url'];
                }
            }

            $condition = $request->input('condition', '');
            $initialSlug = $this->generateAccessorySlug($name, $condition);

            $accessory = Accessory::create([
                'user_id' => $request->input('userId'),
                'name' => $name,
                'description' => $request->input('description'),
                'category' => $request->input('category'),
                'price' => $price,
                'stock' => $request->input('stock', 0),
                'condition' => $condition,
                'location' => $request->input('location'),
                'image_urls' => $imageUrls,
                'slug' => $initialSlug,
                'views' => 0,
                'is_active' => 'true',
                'status' => 'active',
                'cycle_count' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update slug with ID
            $finalSlug = $this->generateAccessorySlug($name, $condition, $accessory->id);
            $accessory->update(['slug' => $finalSlug]);

            $accessoryData = $accessory->toArray();
            $accessoryData['slug'] = $finalSlug;

            return response()->json([
                'message' => 'Accessory created successfully',
                'accessory' => $accessoryData
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating accessory: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error'], 500);
        }
    }

    public function updateAccessory(Request $request, $id)
    {
        try {
            $accessory = Accessory::find($id);

            if (!$accessory) {
                return response()->json(['message' => "No accessory found with ID {$id}."], 404);
            }

            $updates = [];

            if ($request->has('name')) $updates['name'] = $request->input('name');
            if ($request->has('description')) $updates['description'] = $request->input('description');
            if ($request->has('category')) $updates['category'] = $request->input('category');
            if ($request->has('price')) $updates['price'] = $request->input('price');
            if ($request->has('stock')) $updates['stock'] = $request->input('stock');
            if ($request->has('condition')) $updates['condition'] = $request->input('condition');

            if ($request->has('name') || $request->has('condition')) {
                $name = $updates['name'] ?? $accessory->name;
                $condition = $updates['condition'] ?? $accessory->condition ?? '';
                $updates['slug'] = $this->generateAccessorySlug($name, $condition, $id);
            }

            if ($request->hasFile('images')) {
                $cloudinary = $this->getCloudinary();
                
                // Delete old images
                $oldImages = $accessory->image_urls ?? [];
                foreach ($oldImages as $oldImage) {
                    $publicId = $this->extractPublicId($oldImage);
                    if ($publicId) {
                        try {
                            $cloudinary->uploadApi()->destroy($publicId);
                        } catch (\Exception $e) {
                            Log::warning('Error deleting old accessory image: ' . $e->getMessage());
                        }
                    }
                }

                // Upload new images
                $newImageUrls = [];
                foreach ($request->file('images') as $file) {
                    $result = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                        'folder' => 'diksx/accessories',
                        'transformation' => [
                            ['width' => 1000, 'height' => 750, 'crop' => 'fill'],
                            ['quality' => 'auto']
                        ]
                    ]);
                    $newImageUrls[] = $result['secure_url'];
                }
                $updates['image_urls'] = $newImageUrls;
            }

            $accessory->update($updates);

            return response()->json([
                'message' => 'Accessory updated successfully',
                'updatedFields' => $updates
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating accessory with ID {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function deleteAccessory($id)
    {
        try {
            $accessory = Accessory::find($id);

            if (!$accessory) {
                return response()->json(['message' => 'Accessory not found.'], 404);
            }

            $images = $accessory->image_urls ?? [];
            if (!empty($images)) {
                try {
                    $cloudinary = $this->getCloudinary();
                    foreach ($images as $imageUrl) {
                        $publicId = $this->extractPublicId($imageUrl);
                        if ($publicId) {
                            try {
                                $cloudinary->uploadApi()->destroy($publicId);
                            } catch (\Exception $e) {
                                Log::warning('Error deleting accessory image: ' . $e->getMessage());
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Cloudinary initialization error for accessory: ' . $e->getMessage());
                }
            }

            $accessory->delete();

            return response()->json(['message' => 'Accessory deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Error deleting accessory: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function getSellerAccessories(Request $request, $sellerId)
    {
        try {
            $authenticatedUserId = auth('sanctum')->user()?->id;

            if ($authenticatedUserId != $sellerId) {
                return response()->json(['message' => 'Access denied. You can only view your own accessories.'], 403);
            }

            $limit = min((int) $request->query('limit', 100), 500);
            $sortBy = $request->query('sortBy', 'createdAt');
            $order = strtolower($request->query('order', 'desc')) === 'asc' ? 'asc' : 'desc';

            $validSortColumns = ['createdAt' => 'created_at', 'id' => 'id', 'price' => 'price'];
            $sortColumn = $validSortColumns[$sortBy] ?? 'created_at';

            $accessories = Accessory::where('user_id', $sellerId)
                ->orderBy($sortColumn, $order)
                ->limit($limit)
                ->get();

            if ($accessories->isEmpty()) {
                return response()->json(['message' => 'No accessories found for this seller.'], 204);
            }

            return response()->json($accessories);
        } catch (\Exception $e) {
            Log::error('Error fetching seller accessories: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function toggleAccessoriesActiveStatus(Request $request)
    {
        return response()->json(['message' => 'Not implemented'], 200);
    }
}
