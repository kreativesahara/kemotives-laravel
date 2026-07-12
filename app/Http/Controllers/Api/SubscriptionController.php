<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    const PLAN_TYPES = [
        'FREE' => 'free',
        'PAID' => 'paid',
        'CUSTOM' => 'custom'
    ];

    const MANAGEMENT_TYPES = [
        'ADMIN' => 'admin',
        'SELLER' => 'seller'
    ];

    const FREE_PLANS = ['starter'];
    const PAID_PLANS = ['basic', 'growth', 'enterprise', 'custom'];

    private function calculateEndDate($planName)
    {
        return now()->addDays(30);
    }

    private function updateSellerSubscriptionStatus($userId, $hasActiveSubscription)
    {
        Seller::where('user_id', $userId)->update(['has_subscription' => $hasActiveSubscription]);
    }

    public function subscribe(Request $request)
    {
        $userId = $request->input('userId');
        $planName = $request->input('planName');
        $amount = $request->input('amount');
        $currency = $request->input('currency');
        $managedBy = $request->input('managedBy');
        $autoRenewal = $request->input('autoRenewal');

        $newPlanAmount = floatval($amount);
        $planType = $newPlanAmount == 0 ? self::PLAN_TYPES['FREE'] : self::PLAN_TYPES['PAID'];
        $newPlanLower = strtolower($planName);
        $currentDate = now();

        try {
            $activeSub = Subscription::where('user_id', $userId)
                ->where('status', 'active')
                ->first();

            if ($activeSub) {
                $currentPlanLower = strtolower($activeSub->plan_name);
                $currentAmount = floatval($activeSub->amount);

                if ($currentPlanLower === $newPlanLower) {
                    return response()->json([
                        'error' => 'You already have an active subscription for this tier.'
                    ], 400);
                }

                if ($newPlanAmount > $currentAmount) {
                    $endDate = $this->calculateEndDate($planName);

                    $activeSub->update([
                        'plan_name' => $planName,
                        'amount' => $amount,
                        'currency' => $currency,
                        'managed_by' => $managedBy ?? self::MANAGEMENT_TYPES['SELLER'],
                        'auto_renewal' => $autoRenewal ?? ($planType !== self::PLAN_TYPES['FREE']),
                        'plan_type' => $planType,
                        'start_date' => $currentDate,
                        'end_date' => $endDate,
                    ]);

                    $this->updateSellerSubscriptionStatus($userId, $planType === self::PLAN_TYPES['PAID']);

                    return response()->json([
                        'message' => 'Subscription upgraded successfully.',
                        'subscription' => [
                            'id' => $activeSub->id,
                            'planName' => $planName,
                            'amount' => $amount,
                            'currency' => $currency,
                            'status' => 'active',
                            'startDate' => $currentDate,
                            'endDate' => $endDate
                        ]
                    ], 200);
                } else {
                    return response()->json([
                        'error' => 'You can only upgrade to a higher tier than your current subscription.'
                    ], 400);
                }
            } else {
                $endDate = $this->calculateEndDate($planName);
                $initialStatus = in_array($newPlanLower, self::FREE_PLANS) ? 'active' : 'pending';

                $newSubscription = Subscription::create([
                    'user_id' => $userId,
                    'plan_name' => $planName,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => $initialStatus,
                    'managed_by' => $managedBy ?? ($planType === self::PLAN_TYPES['FREE'] ? self::MANAGEMENT_TYPES['ADMIN'] : self::MANAGEMENT_TYPES['SELLER']),
                    'auto_renewal' => $autoRenewal ?? ($planType !== self::PLAN_TYPES['FREE']),
                    'plan_type' => $planType,
                    'start_date' => $currentDate,
                    'end_date' => $endDate,
                ]);

                if ($initialStatus === 'active') {
                    $this->updateSellerSubscriptionStatus($userId, $planType === self::PLAN_TYPES['PAID']);
                }

                return response()->json([
                    'message' => "Subscription " . ($initialStatus === 'active' ? 'created and activated' : 'created') . " successfully.",
                    'subscription' => [
                        'id' => $newSubscription->id,
                        'planName' => $newSubscription->plan_name,
                        'amount' => $newSubscription->amount,
                        'status' => $newSubscription->status,
                        'endDate' => $newSubscription->end_date,
                    ],
                    'requiresPayment' => $initialStatus === 'pending'
                ], 201);
            }
        } catch (\Exception $error) {
            Log::error('Subscription Error: ' . $error->getMessage());
            return response()->json(['error' => 'Failed to create subscription.'], 500);
        }
    }

    public function updateSubscription(Request $request)
    {
        $id = $request->input('id');
        $planName = $request->input('planName');
        $amount = $request->input('amount');
        $currency = $request->input('currency');
        $status = $request->input('status');
        $managedBy = $request->input('managedBy');
        $autoRenewal = $request->input('autoRenewal');

        $newPlanAmount = $amount ? floatval($amount) : 0;
        $newPlanLower = $planName ? strtolower($planName) : null;
        $planType = $newPlanAmount == 0 ? self::PLAN_TYPES['FREE'] : self::PLAN_TYPES['PAID'];

        try {
            $sub = Subscription::find($id);
            if (!$sub) {
                return response()->json(['error' => 'Subscription not found.'], 404);
            }

            $currentPlanLower = strtolower($sub->plan_name);
            $currentAmount = floatval($sub->amount);

            if ($newPlanLower && $newPlanLower === $currentPlanLower) {
                return response()->json(['error' => 'You already have an active subscription for this tier and cannot override it.'], 400);
            }

            if ($newPlanAmount > 0 && $newPlanAmount <= $currentAmount) {
                return response()->json(['error' => 'You can only update to a higher tier than your current subscription.'], 400);
            }

            $updateData = [];
            if ($planName) {
                $updateData['plan_name'] = $planName;
                $updateData['end_date'] = $this->calculateEndDate($planName);
            }
            if ($amount) $updateData['amount'] = $amount;
            if ($currency) $updateData['currency'] = $currency;
            if ($status) $updateData['status'] = $status;
            if ($managedBy) $updateData['managed_by'] = $managedBy;
            if ($autoRenewal !== null) $updateData['auto_renewal'] = $autoRenewal;
            if ($planType) $updateData['plan_type'] = $planType;

            $sub->update($updateData);

            if ($status) {
                $hasActive = ($status === 'active' && $sub->plan_type === self::PLAN_TYPES['PAID']);
                $this->updateSellerSubscriptionStatus($sub->user_id, $hasActive);
            }

            // Remap for frontend
            $mappedSub = [
                'id' => $sub->id,
                'userId' => $sub->user_id,
                'planName' => $sub->plan_name,
                'amount' => $sub->amount,
                'currency' => $sub->currency,
                'status' => $sub->status,
                'managedBy' => $sub->managed_by,
                'autoRenewal' => $sub->auto_renewal,
                'planType' => $sub->plan_type,
                'startDate' => $sub->start_date,
                'endDate' => $sub->end_date,
            ];

            return response()->json([
                'message' => 'Subscription updated successfully.',
                'subscription' => $mappedSub
            ]);
        } catch (\Exception $error) {
            Log::error('Subscription Error: ' . $error->getMessage());
            return response()->json(['error' => 'Failed to update subscription.'], 500);
        }
    }

    public function getSubscription($userId)
    {
        try {
            $subscriptions = Subscription::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($subscriptions->isEmpty()) {
                return response()->json(['message' => 'No subscriptions found for this user.'], 404);
            }

            $currentDate = now();

            foreach ($subscriptions as $sub) {
                if ($sub->end_date && $sub->end_date < $currentDate && $sub->status === 'active') {
                    $sub->update(['status' => 'expired']);
                }
            }

            $activeSubscription = $subscriptions->firstWhere('status', 'active');
            $pendingSubscription = $subscriptions->firstWhere('status', 'pending');

            // Map structures
            $mappedSubs = $subscriptions->map(function ($s) {
                return [
                    'id' => $s->id,
                    'userId' => $s->user_id,
                    'planName' => $s->plan_name,
                    'amount' => $s->amount,
                    'currency' => $s->currency,
                    'status' => $s->status,
                    'startDate' => $s->start_date,
                    'endDate' => $s->end_date,
                ];
            });

            return response()->json([
                'subscriptions' => $mappedSubs,
                'hasActiveSubscription' => $activeSubscription !== null,
                'hasPendingSubscription' => $pendingSubscription !== null,
                'activeSubscription' => $activeSubscription,
                'pendingSubscription' => $pendingSubscription
            ]);
        } catch (\Exception $error) {
            Log::error('Subscription Error: ' . $error->getMessage());
            return response()->json(['error' => 'Failed to fetch subscriptions.'], 500);
        }
    }

    public function cancelSubscription($id)
    {
        try {
            $sub = Subscription::find($id);
            if (!$sub) {
                return response()->json(['error' => 'Subscription not found.'], 404);
            }

            $sub->update(['status' => 'cancelled']);
            $this->updateSellerSubscriptionStatus($sub->user_id, false);

            return response()->json([
                'message' => 'Subscription cancelled successfully.',
                'id' => intval($id)
            ]);
        } catch (\Exception $error) {
            Log::error('Subscription Error: ' . $error->getMessage());
            return response()->json(['error' => 'Failed to cancel subscription.'], 500);
        }
    }

    public function checkSellerStatusAndSubscription(Request $request, $userId)
    {
        $authenticatedUserId = $request->user()->id;

        if ($authenticatedUserId != $userId) {
            return response()->json(['error' => 'Access denied. You can only view your own subscription information.'], 403);
        }

        try {
            $user = User::find($userId);
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $isSeller = $user->roles === 3;

            $subscriptions = Subscription::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            $activeSubscription = $subscriptions->firstWhere('status', 'active');
            $pendingSubscription = $subscriptions->firstWhere('status', 'pending');

            $sellerProfile = null;
            if ($isSeller) {
                $sellerRecord = Seller::where('user_id', $userId)->first();
                if ($sellerRecord) {
                    $sellerProfile = [
                        'id' => $sellerRecord->id,
                        'userId' => $sellerRecord->user_id,
                        'username' => $sellerRecord->username,
                        'accountType' => $sellerRecord->account_type,
                        'contact' => $sellerRecord->contact,
                        'place' => $sellerRecord->place,
                        'imageUrl' => $sellerRecord->image_url,
                    ];
                }
            }

            $mappedActive = $activeSubscription ? [
                'id' => $activeSubscription->id,
                'planName' => $activeSubscription->plan_name,
                'amount' => $activeSubscription->amount,
                'status' => $activeSubscription->status,
                'endDate' => $activeSubscription->end_date,
            ] : null;

            $mappedPending = $pendingSubscription ? [
                'id' => $pendingSubscription->id,
                'planName' => $pendingSubscription->plan_name,
                'amount' => $pendingSubscription->amount,
                'status' => $pendingSubscription->status,
                'endDate' => $pendingSubscription->end_date,
            ] : null;

            $userData = [
                'id' => $user->id,
                'firstName' => $user->firstname ?? '',
                'lastName' => $user->lastname ?? '',
                'email' => $user->email,
                'role' => $user->roles
            ];

            return response()->json([
                'user' => $userData,
                'isSeller' => $isSeller,
                'subscriptionStatus' => $activeSubscription ? 'active' : ($pendingSubscription ? 'pending' : null),
                'requiresPayment' => !$activeSubscription && ($isSeller || $pendingSubscription),
                'activeSubscription' => $mappedActive,
                'pendingSubscription' => $mappedPending,
                'sellerProfile' => $sellerProfile,
                'dataForLocalStorage' => [
                    'user' => $userData,
                    'seller' => $sellerProfile,
                    'subscription' => $mappedActive ?? $mappedPending
                ]
            ]);
        } catch (\Exception $error) {
            Log::error('Subscription Error: ' . $error->getMessage());
            return response()->json(['error' => 'Failed to check seller status and subscription.'], 500);
        }
    }
}
