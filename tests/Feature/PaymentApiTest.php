<?php

use App\Models\User;
use App\Models\Subscription;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use function Pest\Laravel\{postJson, actingAs, withHeaders};

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::create('subscriptions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->string('plan_name');
        $table->string('amount');
        $table->string('currency');
        $table->string('status');
        $table->string('managed_by');
        $table->boolean('auto_renewal');
        $table->string('plan_type');
        $table->string('txnId')->nullable();
        $table->timestamp('start_date');
        $table->timestamp('end_date');
        $table->timestamps();
    });

    Schema::create('payments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
        $table->decimal('amount', 10, 2);
        $table->string('currency', 10)->default('KES');
        $table->string('payment_method', 50);
        $table->string('txn_id', 255)->unique();
        $table->string('status', 20)->default('pending');
        $table->string('customer_name', 255)->nullable();
        $table->string('customer_phone', 50)->nullable();
        $table->string('customer_email', 255)->nullable();
        $table->timestamps();
    });
});

it('initiates a payment', function () {
    $user = User::factory()->create();
    $subscription = Subscription::create([
        'user_id' => $user->id,
        'plan_name' => 'basic',
        'amount' => '1000',
        'currency' => 'KES',
        'status' => 'pending',
        'managed_by' => 'seller',
        'auto_renewal' => true,
        'plan_type' => 'paid',
        'start_date' => now(),
        'end_date' => now()->addDays(30),
    ]);

    $response = actingAs($user)->postJson('/api/payments/initiate', [
        'userId' => $user->id,
        'subscriptionId' => $subscription->id,
        'amount' => '1000',
        'currency' => 'KES',
        'paymentMethod' => 'mpesa'
    ]);

    $response->assertStatus(201);
    
    $response->assertJsonStructure([
        'message',
        'payment' => [
            'id', 'txnId', 'status', 'createdAt'
        ],
        'verifyEndpoint',
        'callbackUrl'
    ]);
});

it('verifies a successful payment', function () {
    $user = User::factory()->create();
    $payment = Payment::create([
        'user_id' => $user->id,
        'amount' => 1000,
        'currency' => 'KES',
        'payment_method' => 'mpesa',
        'txn_id' => 'TXN123',
        'status' => 'pending'
    ]);

    $response = actingAs($user)->postJson('/api/payments/verify', [
        'txnId' => 'TXN123',
        'paymentMethod' => 'mpesa'
    ]);

    // Our mock will just return successful
    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'Payment verified successfully',
        'status' => 'successful',
        'paymentId' => $payment->id
    ]);
});

it('blocks webhook without signature', function () {
    $response = postJson('/api/webhooks/payment', [
        'txnId' => 'TXN123',
        'paymentMethod' => 'stripe',
        'status' => 'successful'
    ]);

    $response->assertStatus(403);
});

it('accepts webhook with valid signature', function () {
    $payload = json_encode([
        'txnId' => 'TXN123',
        'paymentMethod' => 'stripe',
        'status' => 'successful'
    ]);

    $secret = 'test-secret';
    config(['services.payment.webhook_secret' => $secret]);

    $signature = hash_hmac('sha256', $payload, $secret);

    // Create required records
    $user = User::factory()->create();
    $sub = Subscription::create([
        'user_id' => $user->id,
        'plan_name' => 'basic',
        'amount' => '1000',
        'currency' => 'KES',
        'status' => 'pending',
        'managed_by' => 'seller',
        'auto_renewal' => true,
        'plan_type' => 'paid',
        'start_date' => now(),
        'end_date' => now()->addDays(30),
    ]);
    
    $payment = Payment::create([
        'user_id' => $user->id,
        'subscription_id' => $sub->id,
        'amount' => 1000,
        'currency' => 'KES',
        'payment_method' => 'stripe',
        'txn_id' => 'TXN123',
        'status' => 'pending'
    ]);

    $response = $this->call(
        'POST',
        '/api/webhooks/payment',
        [],
        [],
        [],
        [
            'HTTP_X_Signature' => $signature,
            'CONTENT_TYPE' => 'application/json'
        ],
        $payload
    );

    $response->assertStatus(200);
    
    expect($payment->fresh()->status)->toBe('successful');
    expect($sub->fresh()->status)->toBe('active');
});
