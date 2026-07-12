<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kyc;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Cloudinary\Cloudinary;

class KycController extends Controller
{
    private function getCloudinary()
    {
        $cloudinaryUrl = env('CLOUDINARY_URL') ?? "cloudinary://" . env('CLOUDINARY_API_KEY') . ":" . env('CLOUDINARY_API_SECRET') . "@" . env('CLOUDINARY_CLOUD_NAME');
        return new Cloudinary(['cloudinary_url' => $cloudinaryUrl]);
    }

    public function submitKYC(Request $request)
    {
        $userId = $request->input('userId') ?? $request->user()?->id;
        $sellerId = $request->input('sellerId');

        if (!$sellerId && $userId) {
            $sellerRecord = Seller::where('user_id', $userId)->first();
            if (!$sellerRecord) {
                return response()->json(['message' => 'Seller not found for this user'], 404);
            }
            $sellerId = $sellerRecord->id;
        }

        if (!$sellerId) {
            return response()->json(['message' => 'No valid seller ID provided'], 400);
        }

        $existingKyc = Kyc::where('seller_id', $sellerId)->first();
        if ($existingKyc) {
            return response()->json(['message' => 'KYC information already submitted for this seller'], 409);
        }

        // Support both pre-uploaded URLs (legacy) and direct file uploads
        $idDocumentUrl = $request->input('idDocumentUrl');
        $idDocumentPublicId = 'legacy_id_doc';
        $selfieUrl = $request->input('selfieUrl');
        $selfiePublicId = 'legacy_selfie';

        try {
            $cloudinary = null;

            if ($request->hasFile('idDocument')) {
                $cloudinary = $this->getCloudinary();
                $file = $request->file('idDocument');
                $result = $cloudinary->uploadApi()->upload($file->getRealPath(), ['folder' => 'diksx/kyc']);
                $idDocumentUrl = $result['secure_url'];
                $idDocumentPublicId = $result['public_id'];
            }

            if ($request->hasFile('selfie')) {
                $cloudinary = $cloudinary ?? $this->getCloudinary();
                $file = $request->file('selfie');
                $result = $cloudinary->uploadApi()->upload($file->getRealPath(), ['folder' => 'diksx/kyc']);
                $selfieUrl = $result['secure_url'];
                $selfiePublicId = $result['public_id'];
            }
        } catch (\Exception $e) {
            Log::error("Cloudinary upload failed: " . $e->getMessage());
            return response()->json(['message' => 'Failed to upload images'], 500);
        }

        if (!$idDocumentUrl || !$selfieUrl) {
            return response()->json(['message' => 'Please provide both ID document and selfie images'], 400);
        }

        $kyc = Kyc::create([
            'seller_id' => $sellerId,
            'full_name' => $request->input('fullName'),
            'kra_pin' => $request->input('kraPin'),
            'registration_date' => $request->input('registrationDate'),
            'permit_number' => $request->input('permitNumber'),
            'address' => $request->input('address'),
            'id_document_url' => $idDocumentUrl,
            'id_document_public_id' => $idDocumentPublicId,
            'selfie_url' => $selfieUrl,
            'selfie_public_id' => $selfiePublicId,
            'status' => 'pending'
        ]);

        return response()->json([
            'message' => 'KYC information submitted successfully',
            'kyc' => $kyc
        ], 201);
    }

    public function getKYC($sellerId)
    {
        $kyc = Kyc::where('seller_id', $sellerId)->first();
        if (!$kyc) {
            return response()->json(['message' => 'KYC information not found'], 404);
        }
        return response()->json($kyc);
    }

    public function getKYCByUserId(Request $request, $userId)
    {
        if ($request->user() && $request->user()->id != $userId) {
            return response()->json(['message' => 'Access denied. You can only view your own KYC information.'], 403);
        }

        $seller = Seller::where('user_id', $userId)->first();
        if (!$seller) {
            return response()->json(['message' => 'Seller not found'], 404);
        }

        $kyc = Kyc::where('seller_id', $seller->id)->first();
        if (!$kyc) {
            return response()->json(['message' => 'KYC information not found'], 404);
        }

        return response()->json($kyc);
    }

    public function updateKYCStatus(Request $request, $kycId)
    {
        $status = $request->input('status');
        $notes = $request->input('notes');
        $adminId = $request->input('adminId');

        if (!in_array($status, ['pending', 'verified', 'rejected'])) {
            return response()->json(['message' => 'Invalid status'], 400);
        }

        $kyc = Kyc::find($kycId);
        if (!$kyc) {
            return response()->json(['message' => 'KYC record not found'], 404);
        }

        $kyc->update([
            'status' => $status,
            'notes' => $notes,
            'verified_by' => $adminId
        ]);

        if ($status === 'verified') {
            $seller = $kyc->seller;
            if ($seller && $seller->user_id) {
                User::where('id', $seller->user_id)->update(['roles' => 3]);
            }
        }

        return response()->json([
            'message' => 'KYC status updated successfully',
            'kyc' => $kyc
        ]);
    }

    public function getAllKYCs(Request $request)
    {
        $status = $request->input('status');
        $query = Kyc::query();

        if ($status && in_array($status, ['pending', 'verified', 'rejected'])) {
            $query->where('status', $status);
        }

        $kycs = $query->orderBy('created_at')->get();
        return response()->json($kycs);
    }
}
