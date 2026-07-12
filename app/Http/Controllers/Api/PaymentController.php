<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    const PAYMENT_METHODS = [
        'MPESA' => 'mpesa',
        'STRIPE' => 'stripe',
        'PAYPAL' => 'paypal'
    ];

    const PAYMENT_STATUS = [
        'PENDING' => 'pending',
        'SUCCESSFUL' => 'successful',
        'FAILED' => 'failed',
        'EXPIRED' => 'expired'
    ];

    private function processPayment($method, $data)
    {
        $time = time();
        $rand = rand(100, 999);
        switch (strtolower($method)) {
            case self::PAYMENT_METHODS['MPESA']:
                return "MPESA{$time}{$rand}";
            case self::PAYMENT_METHODS['STRIPE']:
                return "STRIPE{$time}{$rand}";
            case self::PAYMENT_METHODS['PAYPAL']:
                return "PAYPAL{$time}{$rand}";
            default:
                throw new \Exception("Unsupported payment method: {$method}");
        }
    }

    private function verifyPaymentWithProvider($method, $txnId)
    {
        switch (strtolower($method)) {
            case self::PAYMENT_METHODS['MPESA']:
            case self::PAYMENT_METHODS['STRIPE']:
            case self::PAYMENT_METHODS['PAYPAL']:
                return self::PAYMENT_STATUS['SUCCESSFUL'];
            default:
                throw new \Exception("Unsupported payment method: {$method}");
        }
    }

    public function initiatePayment(Request $request)
    {
        $userId = $request->input('userId');
        $subscriptionId = $request->input('subscriptionId');
        $amount = $request->input('amount');
        $currency = $request->input('currency');
        $paymentMethod = $request->input('paymentMethod');
        $customerName = $request->input('customerName');
        $customerPhone = $request->input('customerPhone');
        $customerEmail = $request->input('customerEmail');

        if (!$userId || !$subscriptionId || !$amount || !$currency || !$paymentMethod) {
            return response()->json([
                'error' => 'Missing required payment details',
                'required' => ['userId', 'subscriptionId', 'amount', 'currency', 'paymentMethod']
            ], 400);
        }

        try {
            $existingSubscription = Subscription::where('id', $subscriptionId)
                ->where('user_id', $userId)
                ->first();

            if (!$existingSubscription) {
                return response()->json(['error' => 'Subscription not found'], 404);
            }

            if ($existingSubscription->status !== 'pending') {
                return response()->json([
                    'error' => 'Subscription is not in pending status',
                    'status' => $existingSubscription->status
                ], 400);
            }

            $existingPayments = Payment::where('subscription_id', $subscriptionId)->get();

            if ($existingPayments->isNotEmpty()) {
                $successfulPayment = $existingPayments->firstWhere('status', self::PAYMENT_STATUS['SUCCESSFUL']);
                if ($successfulPayment) {
                    return response()->json([
                        'error' => 'Payment already completed for this subscription',
                        'paymentId' => $successfulPayment->id
                    ], 409);
                }

                $pendingPayment = $existingPayments->firstWhere('status', self::PAYMENT_STATUS['PENDING']);
                if ($pendingPayment) {
                    $paymentAge = now()->diffInMinutes($pendingPayment->created_at);
                    if ($paymentAge < 30) {
                        return response()->json([
                            'error' => 'Payment already initiated for this subscription',
                            'paymentId' => $pendingPayment->id
                        ], 409);
                    }

                    $pendingPayment->update(['status' => self::PAYMENT_STATUS['EXPIRED']]);
                }
            }

            try {
                $txnId = $this->processPayment($paymentMethod, [
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                    'name' => $customerName,
                    'amount' => $amount,
                    'currency' => $currency,
                    'subscriptionId' => $subscriptionId
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Payment processing failed',
                    'details' => $e->getMessage()
                ], 400);
            }

            $paymentRecord = Payment::create([
                'user_id' => $userId,
                'subscription_id' => $subscriptionId,
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => $paymentMethod,
                'txn_id' => $txnId,
                'status' => self::PAYMENT_STATUS['PENDING'],
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
            ]);

            return response()->json([
                'message' => 'Payment initiated successfully',
                'payment' => [
                    'id' => $paymentRecord->id,
                    'txnId' => $paymentRecord->txn_id,
                    'status' => $paymentRecord->status,
                    'createdAt' => $paymentRecord->created_at,
                ],
                'verifyEndpoint' => '/api/payments/verify',
                'callbackUrl' => '/api/payments/webhook'
            ], 201);
        } catch (\Exception $e) {
            Log::error("Payment initiation error: " . $e->getMessage());
            return response()->json([
                'error' => 'Payment initiation failed',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        $txnId = $request->input('txnId');
        $paymentMethod = $request->input('paymentMethod');

        if (!$txnId || !$paymentMethod) {
            return response()->json([
                'error' => 'Missing required verification details',
                'required' => ['txnId', 'paymentMethod']
            ], 400);
        }

        try {
            $paymentRecord = Payment::where('txn_id', $txnId)->first();

            if (!$paymentRecord) {
                return response()->json(['error' => 'Payment record not found'], 404);
            }

            if ($paymentRecord->status === self::PAYMENT_STATUS['SUCCESSFUL']) {
                return response()->json([
                    'message' => 'Payment already verified successfully',
                    'status' => self::PAYMENT_STATUS['SUCCESSFUL'],
                    'paymentId' => $paymentRecord->id
                ], 200);
            }

            try {
                $paymentStatus = $this->verifyPaymentWithProvider($paymentMethod, $txnId);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Payment verification failed',
                    'details' => $e->getMessage()
                ], 400);
            }

            if ($paymentStatus === self::PAYMENT_STATUS['SUCCESSFUL']) {
                $paymentRecord->update(['status' => self::PAYMENT_STATUS['SUCCESSFUL']]);

                if ($paymentRecord->subscription_id) {
                    Subscription::where('id', $paymentRecord->subscription_id)->update([
                        'status' => 'active',
                        'txnId' => $txnId
                    ]);
                }

                return response()->json([
                    'message' => 'Payment verified successfully',
                    'status' => self::PAYMENT_STATUS['SUCCESSFUL'],
                    'subscriptionId' => $paymentRecord->subscription_id,
                    'paymentId' => $paymentRecord->id
                ], 200);
            } else {
                return response()->json([
                    'error' => 'Payment verification failed',
                    'status' => $paymentStatus,
                    'txnId' => $txnId
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error("Payment verification error: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to verify payment',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
