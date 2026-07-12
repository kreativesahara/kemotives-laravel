<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Seller;
use App\Models\Car;
use App\Models\Accessory;
use App\Models\CarImage;
use App\Models\Subscription;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class RemainingFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('car_images');
        Schema::dropIfExists('cars');
        Schema::dropIfExists('accessories');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('sellers');

        // Note: users table is already created by RefreshDatabase's default migrations

        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('username')->nullable();
            $table->string('account_type')->nullable();
            $table->string('contact')->nullable();
            $table->string('place')->nullable();
            $table->boolean('has_financing')->default(false);
            $table->boolean('accepts_trade_in')->default(false);
            $table->string('image_url')->nullable();
            $table->boolean('has_subscription')->default(false);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('plan_type');
            $table->string('status');
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users'); // Actually user_id
            $table->string('make');
            $table->string('model');
            $table->string('slug');
            $table->integer('yom');
            $table->string('engine_capacity')->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('transmission')->nullable();
            $table->string('drive_system')->nullable();
            $table->integer('mileage')->nullable();
            $table->text('features')->nullable();
            $table->string('car_condition')->nullable();
            $table->string('vehicle_registration_prefix')->nullable();
            $table->string('vehicle_registration_suffix')->nullable();
            $table->string('view_location')->nullable();
            $table->float('price');
            $table->string('category')->nullable();
            $table->string('is_active')->default('true');
            $table->string('status')->default('active');
            $table->integer('cycle_count')->default(0);
            $table->json('cycle_count_history')->nullable();
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

        Schema::create('accessories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('slug');
            $table->string('condition')->nullable();
            $table->string('location')->nullable();
            $table->float('price');
            $table->integer('stock')->default(0);
            $table->json('image_urls')->nullable();
            $table->integer('views')->default(0);
            $table->string('is_active')->default('true');
            $table->string('status')->default('active');
            $table->integer('cycle_count')->default(0);
            $table->json('cycle_count_history')->nullable();
            $table->timestamps();
        });

        // Create standard user
        $this->user = User::create([
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'roles' => 3
        ]);

        // Create a Seller
        $this->seller = Seller::create([
            'user_id' => $this->user->id,
            'username' => 'Test Dealer',
            'account_type' => 'dealer',
            'contact' => '1234567890',
            'place' => 'Nairobi',
            'has_financing' => true,
            'accepts_trade_in' => false,
            'image_url' => 'http://example.com/seller.jpg',
            'has_subscription' => true
        ]);

        // Create active subscription
        Subscription::create([
            'user_id' => $this->user->id,
            'plan_type' => 'paid',
            'status' => 'active',
            'end_date' => now()->addDays(30)
        ]);

        // Create a Car
        $this->car1 = Car::create([
            'seller_id' => $this->seller->user_id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'slug' => 'toyota-corolla-1',
            'yom' => 2018,
            'engine_capacity' => '1800',
            'fuel_type' => 'petrol',
            'transmission' => 'automatic',
            'drive_system' => '2wd',
            'mileage' => 50000,
            'features' => 'AC, Radio',
            'car_condition' => 'used',
            'vehicle_registration_prefix' => 'KCA',
            'vehicle_registration_suffix' => '123A',
            'view_location' => 'Nairobi',
            'price' => 1500000,
            'category' => 'sedan',
            'is_active' => 'true',
            'status' => 'active',
            'cycle_count' => 1,
            'cycle_count_history' => [],
            'ai_grade' => 'A',
            'ai_score' => 90,
            'ai_notes' => 'Good',
            'views' => 10
        ]);

        // Add a car image so it shows up in FilterController which enforces has('images')
        CarImage::create([
            'car_id' => $this->car1->id,
            'image_url' => 'http://example.com/car.jpg'
        ]);

        // Create an Accessory
        $this->accessory = Accessory::create([
            'user_id' => $this->user->id,
            'name' => 'Dashcam',
            'description' => '1080p',
            'category' => 'Electronics',
            'slug' => 'dashcam-1',
            'condition' => 'new',
            'location' => 'Nairobi',
            'price' => 5000,
            'stock' => 10,
            'image_urls' => ['http://example.com/dashcam.jpg'],
            'views' => 5,
            'is_active' => 'true',
            'status' => 'active',
            'cycle_count' => 0,
            'cycle_count_history' => '[]',
        ]);
    }

    public function test_get_all_accessories_returns_flat_array()
    {
        $response = $this->getJson('/api/accessories');

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertIsArray($data); // Should be a flat array, no "data" wrapper
        $this->assertEquals(1, count($data));
        
        $item = $data[0];
        $this->assertEquals('Dashcam', $item['name']);
        $this->assertEquals(5000, $item['price']);
        $this->assertIsArray($item['imageUrls']); // Ensure JSON column maps properly
        $this->assertEquals('http://example.com/dashcam.jpg', $item['imageUrls'][0]);
    }

    public function test_search_car_returns_flat_array()
    {
        $response = $this->getJson('/api/search?make=Toyota&minPrice=1000000');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertEquals(1, count($data));
        
        $car = $data[0];
        $this->assertEquals('Toyota', $car['make']);
        $this->assertEquals(1500000, $car['price']);
        $this->assertIsArray($car['images']);
    }

    public function test_filter_car_products()
    {
        $response = $this->getJson('/api/filter?make=Toyota&category=sedan');
        
        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertEquals('Toyota', $data[0]['make']);
    }

    public function test_compare_vehicles()
    {
        $id = $this->car1->id;
        $response = $this->getJson("/api/compare?type=vehicles&ids={$id}");
        
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertIsArray($data);
        $this->assertEquals($id, $data[0]['id']);
    }

    public function test_get_sellers_includes_subscription_logic()
    {
        $response = $this->getJson('/api/sellers');
        
        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertIsArray($data);
        
        $seller = $data[0];
        $this->assertEquals('Test Dealer', $seller['username']);
        $this->assertTrue($seller['hasSubscription']);
        $this->assertEquals('1234567890', $seller['phoneNumberToShow']);
    }

    public function test_agent_chat_returns_structured_response()
    {
        $response = $this->postJson('/api/agent/chat', [
            'message' => 'Cheap Toyota sedan in Nairobi under 2m'
        ]);

        $response->assertStatus(200);
        
        $data = $response->json();
        
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('vehicles', $data);
        $this->assertArrayHasKey('parsed', $data);
        $this->assertArrayHasKey('suggestions', $data);
        
        $parsed = $data['parsed'];
        $this->assertEquals('toyota', $parsed['make']);
        $this->assertEquals('nairobi', $parsed['location']);
        $this->assertEquals('sedan', $parsed['category']);
        $this->assertEquals(2000000, $parsed['priceRange']['max']);
        
        $this->assertEquals(1, count($data['vehicles']));
    }
}
