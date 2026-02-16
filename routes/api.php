<?php

/**
 * Student Management API Routes
 * 
 * Authentication Endpoints:
 * - POST /api/register          Register new user
 * - POST /api/login             Login and get token
 * - POST /api/logout            Logout (revoke token)
 * 
 * Public Endpoints:
 * - GET  /api/students          List students (pagination, search, sort)
 * - GET  /api/students/{nim}    Get student by NIM
 * 
 * Protected Endpoints (require Sanctum authentication):
 * - GET    /api/user            Get authenticated user
 * - POST   /api/sync            Trigger data synchronization
 * - DELETE /api/students/{nim}  Soft delete student
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentication endpoints
Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
Route::post('/login', [AuthController::class, 'login'])->name('auth.login');

// Public endpoints - no authentication required
Route::get('/students', [StudentController::class, 'index'])->name('students.index');
Route::get('/students/{nim}', [StudentController::class, 'show'])->name('students.show');

// Protected endpoints - require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/sync', [SyncController::class, 'store'])->name('sync.store');
    Route::delete('/students/{nim}', [StudentController::class, 'destroy'])->name('students.destroy');
});
