<?php

/**
 * Sync API Feature Tests
 * 
 * Note: Static analyzers may show "undefined method" warnings for postJson().
 * These methods are available through the TestCase class configured in Pest.php and work correctly.
 */

use App\Jobs\SyncStudentDataJob;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('sync requires authentication', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/sync');

    $response->assertStatus(401);
});

test('authenticated user can trigger sync synchronously', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Mock HTTP response
    Http::fake([
        '*' => Http::response([
            'RC' => 200,
            'RCM' => 'OK',
            'DATA' => "YMD|NIM|NAMA\n20220803|1234567890|Aiden Adams\n20230415|0987654321|Emma Brooks\n"
        ], 200)
    ]);

    $response = $this->postJson('/api/sync', ['async' => false]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('async', false)
        ->assertJsonStructure(['stats']);

    expect(Student::count())->toBe(2);
});

test('authenticated user can trigger sync asynchronously', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Queue::fake();

    $response = $this->postJson('/api/sync', ['async' => true]);

    $response->assertStatus(202)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('async', true);

    Queue::assertPushed(SyncStudentDataJob::class);
});

test('sync handles successful api response', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Http::fake([
        '*' => Http::response([
            'RC' => 200,
            'RCM' => 'OK',
            'DATA' => "YMD|NIM|NAMA\n20220101|1234567890|Test Student\n"
        ], 200)
    ]);

    $response = $this->postJson('/api/sync');

    $response->assertStatus(200);

    $student = Student::where('nim', '1234567890')->first();
    expect($student)->not->toBeNull();
    expect($student->nama)->toBe('Test Student');
    expect($student->tanggal_lahir->format('Y-m-d'))->toBe('2022-01-01');
});

test('sync handles api error response', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Http::fake([
        '*' => Http::response([
            'RC' => 500,
            'RCM' => 'Internal Server Error'
        ], 200)
    ]);

    $response = $this->postJson('/api/sync');

    $response->assertStatus(500)
        ->assertJsonPath('status', 'error');
});

test('sync handles http connection failure', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Http::fake([
        '*' => Http::response(null, 500)
    ]);

    $response = $this->postJson('/api/sync');

    $response->assertStatus(500);
});

test('sync updates existing students based on nim', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Create existing student
    Student::create([
        'nama' => 'Old Name',
        'nim' => '1234567890',
        'tanggal_lahir' => '2020-01-01'
    ]);

    Http::fake([
        '*' => Http::response([
            'RC' => 200,
            'RCM' => 'OK',
            'DATA' => "YMD|NIM|NAMA\n20220803|1234567890|New Name\n"
        ], 200)
    ]);

    $response = $this->postJson('/api/sync');

    $response->assertStatus(200);

    expect(Student::count())->toBe(1);
    
    $student = Student::where('nim', '1234567890')->first();
    expect($student->nama)->toBe('New Name');
    expect($student->tanggal_lahir->format('Y-m-d'))->toBe('2022-08-03');
});

test('sync validates async parameter', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/sync', ['async' => 'invalid']);

    $response->assertStatus(422);
});

test('sync handles multiple students', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $data = "YMD|NIM|NAMA\n";
    for ($i = 0; $i < 10; $i++) {
        $nim = str_pad((string)$i, 10, '0', STR_PAD_LEFT);
        $data .= "20220101|{$nim}|Student {$i}\n";
    }

    Http::fake([
        '*' => Http::response([
            'RC' => 200,
            'RCM' => 'OK',
            'DATA' => $data
        ], 200)
    ]);

    $response = $this->postJson('/api/sync');

    $response->assertStatus(200);

    expect(Student::count())->toBe(10);
});

test('sync skips invalid records and continues', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Http::fake([
        '*' => Http::response([
            'RC' => 200,
            'RCM' => 'OK',
            'DATA' => "YMD|NIM|NAMA\n20220101|1234567890|Valid Student\ninvalid||Invalid\n20230101|0987654321|Another Valid\n"
        ], 200)
    ]);

    $response = $this->postJson('/api/sync');

    $response->assertStatus(200);

    expect(Student::count())->toBe(2);
    expect(Student::where('nim', '1234567890')->exists())->toBeTrue();
    expect(Student::where('nim', '0987654321')->exists())->toBeTrue();
});
