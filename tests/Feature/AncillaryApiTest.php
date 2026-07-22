<?php

use App\Models\User;
use App\Models\Seller;
use App\Models\Kyc;
use App\Models\Blog;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\{postJson, getJson, patchJson, deleteJson, actingAs};

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::create('sellers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->string('company_name');
        $table->string('email');
        $table->string('phone_number');
        $table->text('address');
        $table->string('logo_url')->nullable();
        $table->string('logo_public_id')->nullable();
        $table->string('description')->nullable();
        $table->integer('status')->default(0); // 0=inactive, 1=active
        $table->boolean('has_subscription')->default(false);
        $table->timestamps();
    });

    Schema::create('kyc', function (Blueprint $table) {
        $table->id();
        $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
        $table->string('full_name');
        $table->string('kra_pin');
        $table->string('registration_date');
        $table->string('permit_number');
        $table->text('address');
        $table->string('id_document_url');
        $table->string('id_document_public_id');
        $table->string('selfie_url');
        $table->string('selfie_public_id');
        $table->string('status')->default('pending'); // pending, verified, rejected
        $table->text('notes')->nullable();
        $table->string('verified_by')->nullable();
        $table->timestamps();
    });

    Schema::create('blogs', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('slug')->unique();
        $table->text('description');
        $table->string('image_url');
        $table->text('content');
        $table->text('backlinks')->nullable();
        $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
        $table->text('meta_description')->nullable();
        $table->text('meta_keywords')->nullable();
        $table->boolean('is_published')->default(false);
        $table->timestamp('published_at')->nullable();
        $table->timestamps();
    });

    Schema::create('votes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('blog_id')->constrained('blogs')->cascadeOnDelete();
        $table->integer('vote'); // 1, -1, 0
        $table->timestamps();

        $table->unique(['user_id', 'blog_id']);
    });
});

it('can submit KYC details', function () {
    $user = User::factory()->create();
    $seller = Seller::forceCreate([
        'user_id' => $user->id,
        'company_name' => 'Test Company',
        'email' => 'test@company.com',
        'phone_number' => '1234567890',
        'address' => '123 Test St',
        'status' => 1,
        'has_subscription' => false
    ]);

    Storage::fake('cloudinary');

    // We use a multipart request simulation with pest
    $response = actingAs($user)->postJson('/api/kyc/submit', [
        'userId' => $user->id,
        'fullName' => 'John Doe',
        'kraPin' => 'A123456789Z',
        'registrationDate' => '2020-01-01',
        'permitNumber' => 'P123',
        'address' => 'Nairobi',
        // Mock images
        'idDocumentUrl' => 'https://mock.com/doc.jpg', // We'll test with direct URLs for ease since testing cloudinary upload requires mocking Cloudinary SDK, but implementation will support both.
        'selfieUrl' => 'https://mock.com/selfie.jpg'
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'kyc' => ['id', 'full_name', 'status']
    ]);
});

it('admin can verify KYC and update user role', function () {
    $user = User::factory()->create(['roles' => 2]); // Role 2 = Buyer initially
    $admin = User::factory()->create(['roles' => 1]); // Admin

    $seller = Seller::forceCreate([
        'user_id' => $user->id,
        'company_name' => 'Test Company',
        'email' => 'test@company.com',
        'phone_number' => '1234567890',
        'address' => '123 Test St',
        'status' => 1,
        'has_subscription' => false
    ]);

    $kyc = Kyc::create([
        'seller_id' => $seller->id,
        'full_name' => 'John Doe',
        'kra_pin' => 'A123456789Z',
        'registration_date' => '2020-01-01',
        'permit_number' => 'P123',
        'address' => 'Nairobi',
        'id_document_url' => 'http://doc.jpg',
        'id_document_public_id' => 'doc1',
        'selfie_url' => 'http://selfie.jpg',
        'selfie_public_id' => 'selfie1',
        'status' => 'pending'
    ]);

    $response = actingAs($admin)->patchJson("/api/kyc/{$kyc->id}/status", [
        'status' => 'verified',
        'adminId' => $admin->id,
        'notes' => 'All good'
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('kyc.status', 'verified');
    
    // Ensure the user's role was upgraded to Seller (3)
    expect($user->fresh()->roles)->toBe(3);
});

it('can fetch published blogs in a flat array', function () {
    Blog::create([
        'title' => 'First Blog',
        'slug' => 'first-blog',
        'description' => 'Test desc',
        'image_url' => 'http://test.com/img.jpg',
        'content' => 'Test content',
        'is_published' => true,
        'published_at' => now()
    ]);

    $response = getJson('/api/blogs');
    $response->assertStatus(200);
    
    // Flat array check
    $json = $response->json();
    expect(is_array($json))->toBeTrue();
    expect($json[0]['title'])->toBe('First Blog');
});

it('can fetch a single blog post as a flat object', function () {
    Blog::create([
        'title' => 'First Blog',
        'slug' => 'first-blog',
        'description' => 'Test desc',
        'image_url' => 'http://test.com/img.jpg',
        'content' => 'Test content',
        'is_published' => true,
        'published_at' => now()
    ]);

    $response = getJson('/api/blogs/first-blog');
    $response->assertStatus(200);
    
    // Flat object check
    $response->assertJsonStructure(['id', 'title', 'slug', 'content']);
});

it('can vote on a blog post', function () {
    $user = User::factory()->create();
    $blog = Blog::create([
        'title' => 'First Blog',
        'slug' => 'first-blog',
        'description' => 'Test desc',
        'image_url' => 'http://test.com/img.jpg',
        'content' => 'Test content',
        'is_published' => true,
        'published_at' => now()
    ]);

    $response = actingAs($user)->postJson("/api/votes/{$blog->id}", [
        'vote' => 1
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'vote' => 1,
        'totalVotes' => 1
    ]);
});

it('returns 0 when a user has not voted', function () {
    $blog = Blog::create([
        'title' => 'First Blog',
        'slug' => 'first-blog',
        'description' => 'Test desc',
        'image_url' => 'http://test.com/img.jpg',
        'content' => 'Test content',
        'is_published' => true,
        'published_at' => now()
    ]);

    $response = getJson("/api/votes/{$blog->id}/user");
    $response->assertStatus(200);
    $response->assertJson(['vote' => 0]);
});

it('returns total votes for a blog post', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $blog = Blog::create([
        'title' => 'First Blog',
        'slug' => 'first-blog',
        'description' => 'Test desc',
        'image_url' => 'http://test.com/img.jpg',
        'content' => 'Test content',
        'is_published' => true,
        'published_at' => now()
    ]);

    Vote::create(['user_id' => $user1->id, 'blog_id' => $blog->id, 'vote' => 1]);
    Vote::create(['user_id' => $user2->id, 'blog_id' => $blog->id, 'vote' => 1]);

    $response = getJson("/api/votes/{$blog->id}/total");
    $response->assertStatus(200);
    $response->assertJson(['totalVotes' => 2]);
});

it('deletes a blog post successfully even with votes attached', function () {
    $user = User::factory()->create();
    $blog = Blog::create([
        'title' => 'Blog To Delete',
        'slug' => 'blog-to-delete',
        'description' => 'Desc',
        'image_url' => 'http://test.com/img.jpg',
        'content' => 'Content',
        'is_published' => true,
        'published_at' => now()
    ]);

    Vote::create(['user_id' => $user->id, 'blog_id' => $blog->id, 'vote' => 1]);

    actingAs($user);

    $response = deleteJson("/api/blogs/{$blog->id}");
    $response->assertStatus(200);

    expect(Blog::find($blog->id))->toBeNull();
    expect(Vote::where('blog_id', $blog->id)->count())->toBe(0);
});
