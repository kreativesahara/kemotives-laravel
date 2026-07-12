<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{postJson, getJson, assertDatabaseHas};

uses(RefreshDatabase::class);

it('registers a user and returns exact expected json shape', function () {
    $response = postJson('/api/auth/register', [
        'firstname' => 'Test',
        'lastname' => 'User',
        'email' => 'test1@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'User registered successfully.',
            'user' => [
                'firstname' => 'Test',
                'lastname' => 'User',
                'email' => 'test1@example.com',
                'roles' => 1,
            ],
        ]);
        
    assertDatabaseHas('users', ['email' => 'test1@example.com']);
});

it('logs in a user, returns token shape, and sets headers/cookies', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
        'roles' => 1,
    ]);

    $response = postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'accessToken',
            'roles',
            'userId',
        ])
        ->assertJsonFragment([
            'roles' => 1,
            'userId' => $user->id,
        ]);

    // Check authorization header
    expect($response->headers->get('authorization'))->not->toBeNull();
    
    // Check refreshToken cookie
    $response->assertCookie('refreshToken');
});

it('refreshes the token and returns exact expected shape', function () {
    $user = User::factory()->create([
        'refreshToken' => 'valid-refresh-token',
        'roles' => 1,
    ]);

    $this->disableCookieEncryption();
    $response = $this->call('GET', '/api/refresh', [], ['refreshToken' => 'valid-refresh-token']);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'firstname',
            'lastname',
            'email',
            'roles',
            'accessToken',
        ])
        ->assertJsonFragment([
            'id' => $user->id,
            'email' => $user->email,
        ]);

    expect($response->headers->get('authorization'))->not->toBeNull();
});

it('logs out user and clears cookies', function () {
    $user = User::factory()->create([
        'refreshToken' => 'valid-refresh-token',
    ]);

    $this->disableCookieEncryption();
    $response = $this->call('GET', '/api/logout', [], ['refreshToken' => 'valid-refresh-token']);

    $response->assertStatus(204);
    $response->assertCookieExpired('refreshToken');
    // authorization header cookie might also be cleared
});
