<?php

/**
 * Student API Feature Tests
 * 
 * Note: Static analyzers may show "undefined method" warnings for getJson(), postJson(), etc.
 * These methods are available through the TestCase class configured in Pest.php and work correctly.
 */

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('can list all students with pagination', function () {
    /** @var \Tests\TestCase $this */
    Student::factory()->count(25)->create();

    $response = $this->getJson('/api/students');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'nama', 'nim', 'tanggal_lahir', 'created_at', 'updated_at']
            ],
            'links',
            'meta'
        ])
        ->assertJsonCount(15, 'data'); // Default pagination
});

test('can search students by nama', function () {
    /** @var \Tests\TestCase $this */
    Student::factory()->create(['nama' => 'Aiden Adams', 'nim' => '1234567890']);
    Student::factory()->create(['nama' => 'Emma Brooks', 'nim' => '0987654321']);

    $response = $this->getJson('/api/students?search=aiden');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama', 'Aiden Adams');
});

test('can search students by nim', function () {
    /** @var \Tests\TestCase $this */
    Student::factory()->create(['nama' => 'Aiden Adams', 'nim' => '1234567890']);
    Student::factory()->create(['nama' => 'Emma Brooks', 'nim' => '0987654321']);

    $response = $this->getJson('/api/students?search=123456');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nim', '1234567890');
});

test('search is case insensitive', function () {
    /** @var \Tests\TestCase $this */
    Student::factory()->create(['nama' => 'Aiden Adams', 'nim' => '1234567890']);

    $response = $this->getJson('/api/students?search=AIDEN');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('can sort students by different fields', function () {
    /** @var \Tests\TestCase $this */
    Student::factory()->create(['nama' => 'Zoe', 'nim' => '3000000000']);
    Student::factory()->create(['nama' => 'Aiden', 'nim' => '1000000000']);

    $response = $this->getJson('/api/students?sort_by=nama&sort_order=asc');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.nama', 'Aiden')
        ->assertJsonPath('data.1.nama', 'Zoe');
});

test('can customize pagination per page', function () {
    /** @var \Tests\TestCase $this */
    Student::factory()->count(30)->create();

    $response = $this->getJson('/api/students?per_page=5');

    $response->assertStatus(200)
        ->assertJsonCount(5, 'data');
});

test('per page has maximum limit of 100', function () {
    /** @var \Tests\TestCase $this */
    Student::factory()->count(150)->create();

    $response = $this->getJson('/api/students?per_page=150');

    $response->assertStatus(422); // Validation error
});

test('can get specific student by nim', function () {
    /** @var \Tests\TestCase $this */
    $student = Student::factory()->create([
        'nama' => 'Aiden Adams',
        'nim' => '1234567890'
    ]);

    $response = $this->getJson('/api/students/1234567890');

    $response->assertStatus(200)
        ->assertJsonPath('data.nim', '1234567890')
        ->assertJsonPath('data.nama', 'Aiden Adams');
});

test('returns 404 when student not found', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/students/9999999999');

    $response->assertStatus(404);
});

test('nim with leading zeros is preserved', function () {
    /** @var \Tests\TestCase $this */
    Student::factory()->create([
        'nama' => 'Test Student',
        'nim' => '0123456789'
    ]);

    $response = $this->getJson('/api/students/0123456789');

    $response->assertStatus(200)
        ->assertJsonPath('data.nim', '0123456789');
});

test('delete requires authentication', function () {
    /** @var \Tests\TestCase $this */
    $student = Student::factory()->create(['nim' => '1234567890']);

    $response = $this->deleteJson('/api/students/1234567890');

    $response->assertStatus(401);
});

test('authenticated user can delete student', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $student = Student::factory()->create(['nim' => '1234567890']);

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/students/1234567890');

    $response->assertStatus(204);
    
    // Verify soft delete
    expect(Student::withTrashed()->where('nim', '1234567890')->count())->toBe(1);
    expect(Student::where('nim', '1234567890')->count())->toBe(0);
});

test('delete returns 404 for non-existent student', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/students/9999999999');

    $response->assertStatus(404);
});

test('deleted students are excluded from list', function () {
    /** @var \Tests\TestCase $this */
    Student::factory()->count(5)->create();
    $student = Student::factory()->create(['nim' => '1234567890']);
    
    $student->delete();

    $response = $this->getJson('/api/students');

    $response->assertStatus(200)
        ->assertJsonCount(5, 'data');
});

test('validation fails for invalid sort_by field', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/students?sort_by=invalid_field');

    $response->assertStatus(422);
});

test('validation fails for invalid sort_order', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/students?sort_order=invalid');

    $response->assertStatus(422);
});
