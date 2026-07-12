<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    const PAYMENT_STATUS_SUCCESSFUL = 'successful';

    public function paymentWebhook(Request $request)
    {
        $txnId = $request->input('txnId');
        $paymentMethod = $request->input('paymentMethod');
        $status = $request->input('status');

        if (!$txnId || !$paymentMethod || !$status) {
            return response()->json([
                'error' => 'Missing required webhook details',
                'required' => ['txnId', 'paymentMethod', 'status']
            ], 400);
        }

        try {
            if ($status !== self::PAYMENT_STATUS_SUCCESSFUL) {
                return response()->json([
                    'error' => 'Invalid payment status received',
                    'status' => $status,
                    'expected' => self::PAYMENT_STATUS_SUCCESSFUL
                ], 400);
            }

            $paymentRecords = Payment::where('txn_id', $txnId)->get();

            if ($paymentRecords->isEmpty()) {
                return response()->json(['error' => 'Payment record not found'], 404);
            }

            // Webhook is idempotent. 
            // If already processed, we just return success.
            $paymentRecord = $paymentRecords->first();
            if ($paymentRecord->status === self::PAYMENT_STATUS_SUCCESSFUL) {
                return response()->json([
                    'message' => 'Payment and subscription status updated successfully',
                    'txnId' => $txnId,
                    'subscriptionId' => $paymentRecord->subscription_id
                ], 200);
            }

            $paymentRecord->update([
                'status' => $status,
                'payment_method' => $paymentMethod
            ]);

            $subId = $paymentRecord->subscription_id;
            if (!$subId) {
                return response()->json(['error' => 'Subscription ID missing in payment record'], 400);
            }

            Subscription::where('id', $subId)->update([
                'status' => 'active',
                'txnId' => $txnId
            ]);

            return response()->json([
                'message' => 'Payment and subscription status updated successfully',
                'txnId' => $txnId,
                'subscriptionId' => $subId
            ], 200);
        } catch (\Exception $e) {
            Log::error("Webhook error: " . $e->getMessage());
            return response()->json([
                'error' => 'Webhook processing failed',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
