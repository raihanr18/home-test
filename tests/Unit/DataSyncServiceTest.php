<?php

use App\Models\Student;
use App\Services\DataSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('parseData extracts records correctly', function () {
    $service = new DataSyncService();
    
    $data = "YMD|NIM|NAMA\n20220803|1234567890|Aiden Adams\n20230415|0987654321|Emma Brooks\n";
    
    $records = $service->parseData($data);
    
    expect($records)->toHaveCount(2);
    expect($records->first())->toMatchArray([
        'nama' => 'Aiden Adams',
        'nim' => '1234567890',
        'tanggal_lahir' => '2022-08-03'
    ]);
});

test('parseData handles empty lines', function () {
    $service = new DataSyncService();
    
    $data = "YMD|NIM|NAMA\n20220803|1234567890|Aiden Adams\n\n\n20230415|0987654321|Emma Brooks\n";
    
    $records = $service->parseData($data);
    
    expect($records)->toHaveCount(2);
});

test('parseData skips malformed lines', function () {
    $service = new DataSyncService();
    
    $data = "YMD|NIM|NAMA\n20220803|1234567890|Aiden Adams\nInvalid Line\n20230415|0987654321|Emma Brooks\n";
    
    $records = $service->parseData($data);
    
    expect($records)->toHaveCount(2);
});

test('parseData validates nim must be 10 digits', function () {
    $service = new DataSyncService();
    
    $data = "YMD|NIM|NAMA\n20220803|1234567890|Valid\n20220803|123|Invalid\n";
    
    $records = $service->parseData($data);
    
    expect($records)->toHaveCount(1);
    expect($records->first()['nim'])->toBe('1234567890');
});

test('parseData validates nim is numeric', function () {
    $service = new DataSyncService();
    
    $data = "YMD|NIM|NAMA\n20220803|1234567890|Valid\n20220803|abcdefghij|Invalid\n";
    
    $records = $service->parseData($data);
    
    expect($records)->toHaveCount(1);
});

test('parseData preserves leading zeros in nim', function () {
    $service = new DataSyncService();
    
    $data = "YMD|NIM|NAMA\n20220803|0123456789|Test\n";
    
    $records = $service->parseData($data);
    
    expect($records->first()['nim'])->toBe('0123456789');
});

test('parseData validates date format', function () {
    $service = new DataSyncService();
    
    $data = "YMD|NIM|NAMA\n20220803|1234567890|Valid\n2022-08-03|0987654321|Invalid\n";
    
    $records = $service->parseData($data);
    
    expect($records)->toHaveCount(1);
});

test('parseData transforms date correctly', function () {
    $service = new DataSyncService();
    
    $data = "YMD|NIM|NAMA\n20220803|1234567890|Test\n";
    
    $records = $service->parseData($data);
    
    expect($records->first()['tanggal_lahir'])->toBe('2022-08-03');
});

test('parseData handles invalid dates', function () {
    $service = new DataSyncService();
    
    $data = "YMD|NIM|NAMA\n20220803|1234567890|Valid\n20229999|0987654321|Invalid\n";
    
    $records = $service->parseData($data);
    
    expect($records)->toHaveCount(1);
});

test('parseData validates nama is not empty', function () {
    $service = new DataSyncService();
    
    $data = "YMD|NIM|NAMA\n20220803|1234567890|Valid Name\n20220803|0987654321|\n";
    
    $records = $service->parseData($data);
    
    expect($records)->toHaveCount(1);
});

test('parseData handles nama with spaces', function () {
    $service = new DataSyncService();
    
    $data = "YMD|NIM|NAMA\n20220803|1234567890|Aiden Adams Baker\n";
    
    $records = $service->parseData($data);
    
    expect($records->first()['nama'])->toBe('Aiden Adams Baker');
});

test('fetchAndSync creates new students', function () {
    Http::fake([
        '*' => Http::response([
            'RC' => 200,
            'RCM' => 'OK',
            'DATA' => "YMD|NIM|NAMA\n20220803|1234567890|Aiden Adams\n"
        ], 200)
    ]);

    $service = app(DataSyncService::class);
    $stats = $service->fetchAndSync();

    expect($stats['total'])->toBe(1);
    expect($stats['created'])->toBe(1);
    expect($stats['updated'])->toBe(0);
    expect(Student::count())->toBe(1);
});

test('fetchAndSync updates existing students', function () {
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

    $service = app(DataSyncService::class);
    $stats = $service->fetchAndSync();

    expect($stats['total'])->toBe(1);
    expect($stats['created'])->toBe(0);
    expect($stats['updated'])->toBe(1);
    expect(Student::count())->toBe(1);
    
    $student = Student::first();
    expect($student->nama)->toBe('New Name');
});

test('fetchAndSync handles mixed create and update', function () {
    Student::create([
        'nama' => 'Existing',
        'nim' => '1234567890',
        'tanggal_lahir' => '2020-01-01'
    ]);

    Http::fake([
        '*' => Http::response([
            'RC' => 200,
            'RCM' => 'OK',
            'DATA' => "YMD|NIM|NAMA\n20220803|1234567890|Updated\n20230415|0987654321|New Student\n"
        ], 200)
    ]);

    $service = app(DataSyncService::class);
    $stats = $service->fetchAndSync();

    expect($stats['total'])->toBe(2);
    expect($stats['created'])->toBe(1);
    expect($stats['updated'])->toBe(1);
    expect(Student::count())->toBe(2);
});

test('fetchAndSync throws exception when API URL not configured', function () {
    config(['services.external_api.url' => null]);

    $service = app(DataSyncService::class);

    expect(fn() => $service->fetchAndSync())->toThrow(Exception::class);
});

test('fetchAndSync throws exception on HTTP error', function () {
    Http::fake([
        '*' => Http::response(null, 500)
    ]);

    $service = app(DataSyncService::class);

    expect(fn() => $service->fetchAndSync())->toThrow(Exception::class);
});

test('fetchAndSync throws exception when RC is not 200', function () {
    Http::fake([
        '*' => Http::response([
            'RC' => 500,
            'RCM' => 'Error'
        ], 200)
    ]);

    $service = app(DataSyncService::class);

    expect(fn() => $service->fetchAndSync())->toThrow(Exception::class);
});

test('fetchAndSync throws exception when DATA field is missing', function () {
    Http::fake([
        '*' => Http::response([
            'RC' => 200,
            'RCM' => 'OK'
        ], 200)
    ]);

    $service = app(DataSyncService::class);

    expect(fn() => $service->fetchAndSync())->toThrow(Exception::class);
});
