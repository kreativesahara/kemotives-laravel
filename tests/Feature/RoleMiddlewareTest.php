<?php

use App\Models\User;
use App\Enums\Role;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{getJson, actingAs};

uses(RefreshDatabase::class);

beforeEach(function () {
    // Define a dummy route protected by the role middleware
    Route::middleware(['auth:sanctum', 'role:3,4,5'])->get('/api/protected', function () {
        return response()->json(['message' => 'Success']);
    });
});

it('blocks unauthenticated requests', function () {
    $response = getJson('/api/protected');
    // Auth sanctum default behavior might redirect to login if not 'api' prefix, but we used getJson so it returns 401
    $response->assertStatus(401);
});

it('blocks authenticated users without the required roles', function () {
    $user = User::factory()->create([
        'roles' => Role::Visitor->value, // 1
    ]);

    $response = actingAs($user, 'sanctum')->getJson('/api/protected');
    
    $response->assertStatus(401)
        ->assertJson(['error' => 'Unauthorized: Role mismatch']);
});

it('allows authenticated users with a required role', function () {
    $user = User::factory()->create([
        'roles' => Role::Seller->value, // 3
    ]);

    $response = actingAs($user, 'sanctum')->getJson('/api/protected');
    
    $response->assertStatus(200)
        ->assertJson(['message' => 'Success']);
});
