<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Cloudinary\Cloudinary;

class SellersController extends Controller
{
    private function getCloudinary()
    {
        $cloudinaryUrl = env('CLOUDINARY_URL') ?? "cloudinary://" . env('CLOUDINARY_API_KEY') . ":" . env('CLOUDINARY_API_SECRET') . "@" . env('CLOUDINARY_CLOUD_NAME');
        return new Cloudinary(['cloudinary_url' => $cloudinaryUrl]);
    }

    private function hasActiveSubscription($userId)
    {
        $activeSub = Subscription::where('user_id', $userId)
            ->where('status', 'active')
            ->first();
            
        return $activeSub && $activeSub->plan_type === 'paid' && $activeSub->status === 'active';
    }

    public function getAllSellers()
    {
        try {
            $sellers = Seller::all();
            
            if ($sellers->isEmpty()) {
                return response()->json(['message' => 'No sellers found.'], 204);
            }
            
            $sellersWithSubscription = $sellers->map(function ($seller) {
                $isPaidSubscription = $this->hasActiveSubscription($seller->user_id);
                
                $data = $seller->toArray();
                $data['hasSubscription'] = $isPaidSubscription;
                $data['phoneNumberToShow'] = $isPaidSubscription ? $seller->contact : 'ADMIN_CONTACT';
                
                return $data;
            });
            
            return response()->json($sellersWithSubscription);
        } catch (\Exception $e) {
            Log::error('Error fetching sellers: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function getSeller($id)
    {
        try {
            $seller = Seller::where('user_id', $id)->first();
            
            if (!$seller) {
                return response()->json(['message' => 'Seller not found.'], 404);
            }
            
            $isPaidSubscription = $this->hasActiveSubscription($id);
            
            $data = $seller->toArray();
            $data['hasSubscription'] = $isPaidSubscription;
            $data['phoneNumberToShow'] = $isPaidSubscription ? $seller->contact : 'ADMIN_CONTACT';
            
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error fetching seller: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function createSeller(Request $request)
    {
        $username = $request->input('username');
        $place = $request->input('place');
        $userId = $request->input('userId');
        
        if (!$username || !$place || !$userId || !$request->hasFile('image')) {
            return response()->json(['message' => 'Username, place, image, and userId are required.'], 400);
        }

        try {
            $user = User::find($userId);
            if (!$user) {
                return response()->json(['message' => 'User not found.'], 404);
            }
            
            if ((int)$user->roles !== 3) {
                $user->update(['roles' => 3]);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating user role.'], 500);
        }

        $hasActiveSubscription = $this->hasActiveSubscription($userId);

        try {
            $file = $request->file('image');
            $cloudinary = $this->getCloudinary();
            $result = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder' => "diksx/sellers/{$userId}",
                'public_id' => Str::slug($username),
                'overwrite' => true
            ]);

            $seller = Seller::create([
                'user_id' => $userId,
                'username' => $username,
                'account_type' => $request->input('accountType'),
                'contact' => $request->input('contact'),
                'place' => $place,
                'has_financing' => filter_var($request->input('hasFinancing'), FILTER_VALIDATE_BOOLEAN),
                'accepts_trade_in' => filter_var($request->input('acceptsTradeIn'), FILTER_VALIDATE_BOOLEAN),
                'image_url' => $result['secure_url'],
                'has_subscription' => $hasActiveSubscription
            ]);

            $sellerData = $seller->toArray();
            $sellerData['phoneNumberToShow'] = $hasActiveSubscription ? $seller->contact : 'ADMIN_CONTACT';

            return response()->json([
                'message' => 'Seller created successfully.',
                'seller' => $sellerData
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating seller: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function updateSeller(Request $request, $id)
    {
        try {
            $seller = Seller::where('user_id', $id)->first();
            if (!$seller) {
                return response()->json(['message' => 'Seller not found.'], 404);
            }

            $hasActiveSubscription = $this->hasActiveSubscription($id);

            $updates = [];
            if ($request->has('username')) $updates['username'] = $request->input('username');
            if ($request->has('accountType')) $updates['account_type'] = $request->input('accountType');
            if ($request->has('contact')) $updates['contact'] = $request->input('contact');
            if ($request->has('place')) $updates['place'] = $request->input('place');
            if ($request->has('hasFinancing')) $updates['has_financing'] = filter_var($request->input('hasFinancing'), FILTER_VALIDATE_BOOLEAN);
            if ($request->has('acceptsTradeIn')) $updates['accepts_trade_in'] = filter_var($request->input('acceptsTradeIn'), FILTER_VALIDATE_BOOLEAN);
            
            $updates['has_subscription'] = $hasActiveSubscription;

            if ($request->hasFile('image')) {
                $cloudinary = $this->getCloudinary();
                
                // Extract old public ID and delete
                $oldUrl = $seller->image_url;
                if ($oldUrl) {
                    $parts = explode('/', $oldUrl);
                    $filename = end($parts);
                    $publicId = explode('.', $filename)[0];
                    if ($publicId) {
                        try {
                            $cloudinary->uploadApi()->destroy("diksx/sellers/{$id}/{$publicId}");
                        } catch (\Exception $e) {}
                    }
                }

                $file = $request->file('image');
                $result = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                    'folder' => "diksx/sellers/{$id}",
                    'public_id' => Str::slug($updates['username'] ?? $seller->username),
                    'overwrite' => true
                ]);
                $updates['image_url'] = $result['secure_url'];
            }

            $seller->update($updates);

            $sellerData = $seller->toArray();
            $sellerData['phoneNumberToShow'] = $hasActiveSubscription ? $seller->contact : 'ADMIN_CONTACT';

            return response()->json([
                'message' => 'Seller updated successfully.',
                'seller' => $sellerData
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating seller: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function deleteSeller($id)
    {
        try {
            $seller = Seller::where('user_id', $id)->first();
            if (!$seller) {
                return response()->json(['message' => 'Seller not found.'], 404);
            }

            $oldUrl = $seller->image_url;
            if ($oldUrl) {
                $parts = explode('/', $oldUrl);
                $filename = end($parts);
                $publicId = explode('.', $filename)[0];
                if ($publicId) {
                    try {
                        $cloudinary = $this->getCloudinary();
                        $cloudinary->uploadApi()->destroy("diksx/sellers/{$id}/{$publicId}");
                    } catch (\Exception $e) {}
                }
            }

            // Track deletion could be implemented here by inserting into a deleted_sellers table
            
            $seller->delete();
            return response()->json(['message' => 'Seller deleted successfully.']);
        } catch (\Exception $e) {
            Log::error('Error deleting seller: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }
}
