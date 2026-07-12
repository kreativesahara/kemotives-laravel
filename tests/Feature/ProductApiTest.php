<?php

use App\Models\User;
use App\Models\Car;
use App\Models\Seller;
use App\Models\CarImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use function Pest\Laravel\{getJson, postJson, putJson, patchJson, deleteJson, actingAs};

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::create('sellers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
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

    Schema::create('cars', function (Blueprint $table) {
        $table->id();
        $table->foreignId('seller_id')->constrained('sellers', 'user_id')->cascadeOnDelete();
        $table->string('make');
        $table->string('model');
        $table->string('slug')->unique();
        $table->integer('yom');
        $table->string('engine_capacity');
        $table->string('fuel_type');
        $table->string('transmission');
        $table->string('drive_system');
        $table->text('mileage');
        $table->text('features');
        $table->string('car_condition');
        $table->string('vehicle_registration_prefix')->nullable();
        $table->string('vehicle_registration_suffix')->nullable();
        $table->string('view_location');
        $table->text('price'); // varchar stored
        $table->string('category');
        $table->string('is_active')->default("true");
        $table->string('status')->default("active");
        $table->integer('cycle_count')->default(0);
        $table->text('cycle_count_history')->default("[]");
        $table->string('ai_grade')->nullable();
        $table->integer('ai_score')->nullable();
        $table->text('ai_notes')->nullable();
        $table->integer('views')->default(0);
        $table->timestamps();
    });

    Schema::create('car_images', function (Blueprint $table) {
        $table->id();
        $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();
        $table->string('image_url');
        $table->timestamps();
    });
});

it('public products return a flat array with exact camelCase mapped keys', function () {
    $user = User::factory()->create();
    $seller = Seller::create([
        'user_id' => $user->id,
        'username' => 'TestDealer',
        'account_type' => 'Dealer',
        'place' => 'Nairobi',
        'image_url' => 'http://example.com/seller.jpg'
    ]);

    $car = Car::create([
        'seller_id' => $seller->user_id,
        'make' => 'Toyota',
        'model' => 'Camry',
        'slug' => 'toyota-camry',
        'yom' => 2020,
        'engine_capacity' => '2.5L',
        'fuel_type' => 'Petrol',
        'transmission' => 'Automatic',
        'drive_system' => '2WD',
        'mileage' => '10000',
        'features' => 'AC,Bluetooth',
        'car_condition' => 'Used',
        'view_location' => 'Nairobi',
        'price' => '2000000',
        'category' => 'Sedan',
        'is_active' => 'true'
    ]);

    CarImage::create([
        'car_id' => $car->id,
        'image_url' => 'http://example.com/image.jpg'
    ]);

    $response = getJson('/api/publicproducts');
    $response->assertStatus(200);
    $response->assertJsonIsArray();
    
    // Ensure data is not wrapped and camelCase is maintained
    $response->assertJsonStructure([
        '*' => [
            'id', 'sellerId', 'make', 'model', 'slug', 'year', 'engineCapacity',
            'fuelType', 'transmission', 'driveSystem', 'mileage', 'features',
            'condition', 'vehicleRegPrefix', 'vehicleRegSuffix', 'location',
            'price', 'category', 'views', 'isActive', 'status', 'cycleCount',
            'cycleCountHistory', 'createdAt', 'updatedAt', 'aiGrade', 'aiScore',
            'aiNotes', 'images'
        ]
    ]);

    // Ensure images are a flat array of strings, not objects
    $data = $response->json();
    expect($data[0]['images'])->toBeArray();
    expect($data[0]['images'][0])->toBe('http://example.com/image.jpg');
    expect($data[0]['year'])->toBe(2020);
});

it('single product returns flat object with productSeller', function () {
    $user = User::factory()->create();
    $seller = Seller::create([
        'user_id' => $user->id,
        'username' => 'TestDealer',
        'account_type' => 'Dealer',
        'place' => 'Nairobi',
        'image_url' => 'http://example.com/seller.jpg'
    ]);

    $car = Car::create([
        'seller_id' => $seller->user_id,
        'make' => 'Toyota',
        'model' => 'Camry',
        'slug' => 'toyota-camry-2',
        'yom' => 2020,
        'engine_capacity' => '2.5L',
        'fuel_type' => 'Petrol',
        'transmission' => 'Automatic',
        'drive_system' => '2WD',
        'mileage' => '10000',
        'features' => 'AC,Bluetooth',
        'car_condition' => 'Used',
        'view_location' => 'Nairobi',
        'price' => '2000000',
        'category' => 'Sedan',
        'is_active' => 'true'
    ]);

    $response = getJson('/api/product/toyota-camry-2');
    $response->assertStatus(200);
    
    $response->assertJsonStructure([
        'id', 'make', 'images', 'productSeller' => [
            // The productSeller array structure expected by frontend
            // Express returned: [{ id: 1, userId: 1, username: '...' }]
            // Wait, Express returned `productSeller: [ { ... } ]` because it did a select without [0]!
            // Let's verify what Express returned for productSeller.
            '*' => ['id', 'userId', 'username', 'accountType', 'place'] // wait, did Express map sellers?
        ]
    ]);
});
