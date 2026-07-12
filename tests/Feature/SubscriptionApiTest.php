<?php

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use function Pest\Laravel\{postJson, getJson, actingAs};

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
        $table->timestamp('start_date');
        $table->timestamp('end_date');
        $table->timestamps();
    });

    Schema::create('sellers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
        $table->text('username');
        $table->string('account_type')->nullable();
        $table->string('contact')->nullable();
        $table->string('place');
        $table->string('image_url');
        $table->string('has_financing')->nullable();
        $table->string('accepts_trade_in')->nullable();
        $table->boolean('has_subscription')->default(false);
        $table->timestamps();
    });
});

it('creates a new paid subscription and sets status to pending', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->postJson('/api/subscriptions', [
        'userId' => $user->id,
        'planName' => 'enterprise',
        'amount' => '1000',
        'currency' => 'KES',
    ]);

    $response->assertStatus(201);
    
    // Assert exactly the shape Express returns
    $response->assertJsonStructure([
        'message',
        'subscription' => [
            'id', 'planName', 'amount', 'status', 'endDate'
        ],
        'requiresPayment'
    ]);

    $data = $response->json();
    expect($data['requiresPayment'])->toBeTrue();
    expect($data['subscription']['status'])->toBe('pending');
});

it('creates a new free subscription and sets status to active', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->postJson('/api/subscriptions', [
        'userId' => $user->id,
        'planName' => 'starter',
        'amount' => '0',
        'currency' => 'KES',
    ]);

    $response->assertStatus(201);
    
    $data = $response->json();
    expect($data['requiresPayment'])->toBeFalse();
    expect($data['subscription']['status'])->toBe('active');
});

it('blocks subscribing to the same tier', function () {
    $user = User::factory()->create();
    
    Subscription::create([
        'user_id' => $user->id,
        'plan_name' => 'starter',
        'amount' => '0',
        'currency' => 'KES',
        'status' => 'active',
        'managed_by' => 'admin',
        'auto_renewal' => false,
        'plan_type' => 'free',
        'start_date' => now(),
        'end_date' => now()->addDays(30),
    ]);

    $response = actingAs($user)->postJson('/api/subscriptions', [
        'userId' => $user->id,
        'planName' => 'starter',
        'amount' => '0',
        'currency' => 'KES',
    ]);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'You already have an active subscription for this tier.']);
});
