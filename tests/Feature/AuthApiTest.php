<?php

/**
 * Authentication API Feature Tests
 * 
 * Tests for user registration, login, and logout functionality.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can register with valid data', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email'],
            'access_token',
            'token_type',
        ])
        ->assertJsonPath('user.name', 'John Doe')
        ->assertJsonPath('user.email', 'john@example.com')
        ->assertJsonPath('token_type', 'Bearer');

    expect(User::where('email', 'john@example.com')->exists())->toBeTrue();
});

test('registration fails with missing name', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/register', [
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('registration fails with missing email', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registration fails with invalid email format', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'invalid-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registration fails with duplicate email', function () {
    /** @var \Tests\TestCase $this */
    User::factory()->create(['email' => 'john@example.com']);

    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registration fails with missing password', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registration fails with password mismatch', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different_password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('user can login with valid credentials', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email'],
            'access_token',
            'token_type',
        ])
        ->assertJsonPath('user.email', 'john@example.com')
        ->assertJsonPath('token_type', 'Bearer');
});

test('login fails with invalid email', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login fails with invalid password', function () {
    /** @var \Tests\TestCase $this */
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'wrong_password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login fails with missing email', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/login', [
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login fails with missing password', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/login', [
        'email' => 'john@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('authenticated user can logout', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/logout');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Logout successful');
});

test('logout requires authentication', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/logout');

    $response->assertStatus(401);
});

test('logout revokes only current token', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    
    // Create two tokens
    $token1 = $user->createToken('device1')->plainTextToken;
    $token2 = $user->createToken('device2')->plainTextToken;
    
    // Verify user has 2 tokens
    expect($user->tokens()->count())->toBe(2);
    
    // Logout using first token
    $response = $this->withHeader('Authorization', "Bearer $token1")
        ->postJson('/api/logout');
    
    $response->assertStatus(200);
    
    // Verify only 1 token remains
    $user->refresh();
    expect($user->tokens()->count())->toBe(1);
    
    // Second token should still work
    $response = $this->withHeader('Authorization', "Bearer $token2")
        ->getJson('/api/user');
    $response->assertStatus(200);
});

test('registered user receives valid token', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $token = $response->json('access_token');

    // Test that token works
    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/user');

    $response->assertStatus(200)
        ->assertJsonPath('email', 'john@example.com');
});

test('login token can be used for authentication', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $token = $response->json('access_token');

    // Test that token works
    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/user');

    $response->assertStatus(200)
        ->assertJsonPath('email', 'john@example.com');
});

test('password is hashed in database', function () {
    /** @var \Tests\TestCase $this */
    $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::where('email', 'john@example.com')->first();
    
    expect($user->password)->not->toBe('password123');
    expect(strlen($user->password))->toBeGreaterThan(50); // Hashed passwords are long
});
