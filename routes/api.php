<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\SubscriberController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\GoalsController;
use App\Http\Controllers\Api\MissionAndVisionController;
use App\Http\Controllers\Api\ObjectiveController;
use App\Http\Controllers\Api\OfficeContactController;
use App\Http\Controllers\Api\BusinessPartnerController;
use App\Http\Controllers\Api\LegitimacyController;

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Public routes - NO /api prefix needed (Laravel adds it automatically)
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Shortcut route for PDF export
    Route::get('/export-pdf', [UserController::class, 'exportPDF']);

    // Original routes
    Route::get('/users/statistics', [UserController::class, 'statistics']);
    Route::get('/users/export/pdf', [UserController::class, 'exportPDF']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::patch('/users/{id}/status', [UserController::class, 'updateStatus']);
});

// ===================================
// APPLICATION ROUTES (All Protected)





// Public route - Get approved business partners (no auth required)
Route::get('business-partners', [BusinessPartnerController::class, 'index']);



// MEMBERS
// Protected routes - Require authentication
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Member routes for business partners
    Route::prefix('user')->group(function () {
        Route::get('business-partners', [BusinessPartnerController::class, 'userIndex']);
    });

    Route::post('business-partners', [BusinessPartnerController::class, 'store']);
    Route::put('business-partners/{id}', [BusinessPartnerController::class, 'userUpdate']);
    Route::delete('business-partners/{id}', [BusinessPartnerController::class, 'userDestroy']);

    // Admin routes for business partners
    Route::prefix('admin')->group(function () {
        Route::get('business-partners', [BusinessPartnerController::class, 'adminIndex']);
        Route::put('business-partners/{id}', [BusinessPartnerController::class, 'adminUpdate']);
        Route::delete('business-partners/{id}', [BusinessPartnerController::class, 'adminDestroy']);
    });
});



Route::middleware('auth:sanctum')->group(function () {
    // member legitimacy request routes
    Route::get('legitimacy', [LegitimacyController::class, 'userIndex']);
    Route::post('legitimacy', [LegitimacyController::class, 'userStore']);
    Route::put('legitimacy/{id}', [LegitimacyController::class, 'userUpdate']);


});


Route::middleware(['auth:sanctum'])->group(function () {
    // Admin routes
    Route::prefix('admin')->group(function () {
        Route::get('/legitimacy', [LegitimacyController::class, 'adminIndex']);
        Route::post('/legitimacy', [LegitimacyController::class, 'adminStore']);
        Route::put('/legitimacy/{id}', [LegitimacyController::class, 'adminUpdate']);
        Route::post('/legitimacy/{id}', [LegitimacyController::class, 'adminUpdate']); // For form data with _method
        Route::delete('/legitimacy/{id}', [LegitimacyController::class, 'adminDestroy']);
    });
});


Route::middleware(['auth:sanctum'])->group(function () {
    // ... existing routes
    Route::get('/admin/legitimacy/{id}/pdf', [LegitimacyController::class, 'generatePDF']);
});





Route::post('/contacts', [ContactController::class, 'store']);

// Protected routes (add your authentication middleware)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::get('/contacts/{id}', [ContactController::class, 'show']);
    Route::patch('/contacts/{id}/status', [ContactController::class, 'updateStatus']);
    Route::post('/admin/contacts/{id}/reply', [ContactController::class, 'reply']);
});



Route::prefix('news')->group(function () {
    Route::get('/published', [NewsController::class, 'published']);
    Route::get('/published/{id}', [NewsController::class, 'show']);
});

// Admin routes - Require authentication
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('admin')->group(function () {
        Route::prefix('news')->group(function () {
            Route::get('/', [NewsController::class, 'index']);
            Route::post('/', [NewsController::class, 'store']);
            Route::get('/{id}', [NewsController::class, 'show']);
            Route::post('/{id}', [NewsController::class, 'update']);
            Route::put('/{id}', [NewsController::class, 'update']);
            Route::delete('/{id}', [NewsController::class, 'destroy']);
        });
    });
});

Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::get('/announcements/{id}', [AnnouncementController::class, 'show']);

// Protected routes - admin only
Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('announcements', AnnouncementController::class);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::patch('/announcements/{id}', [AnnouncementController::class, 'update']);
    Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy']);
    Route::post('/announcements/{id}/toggle-active', [AnnouncementController::class, 'toggleActive']);
});

Route::get('/subscribers/active', [SubscriberController::class, 'getActiveSubscribers']);
Route::prefix('subscribers')->group(function () {
    Route::post('subscribe', [SubscriberController::class, 'subscribe']);
    Route::get('verify/{token}', [SubscriberController::class, 'verify']);
    Route::get('unsubscribe/{token}', [SubscriberController::class, 'unsubscribe']);
    Route::get('active', [SubscriberController::class, 'getActiveSubscribers']);
});

// Admin subscriber routes (protected)
Route::middleware(['auth:sanctum'])->prefix('admin/subscribers')->group(function () {
    Route::get('/', [SubscriberController::class, 'index']);
    Route::get('statistics', [SubscriberController::class, 'statistics']);
    Route::delete('{id}', [SubscriberController::class, 'destroy']);
});


Route::middleware('auth:sanctum')->prefix('our-community')->group(function () {
    Route::get('/', [CommunityController::class, 'index']);
    Route::post('/', [CommunityController::class, 'store']);
    Route::put('/', [CommunityController::class, 'update']);
    Route::delete('/', [CommunityController::class, 'destroy']);
});

// Protected admin routes - require authentication
Route::middleware('auth:sanctum')->prefix('goals')->group(function () {
    Route::get('/show', [GoalsController::class, 'show']);
    Route::post('/', [GoalsController::class, 'store']);
    Route::put('/', [GoalsController::class, 'update']);
    Route::delete('/', [GoalsController::class, 'destroy']);
});

// Public mission and vision routes - no require authentication
Route::get('/mission-and-vision', [MissionAndVisionController::class, 'index']);

// Protected mission and vision routes - require authentication
Route::middleware('auth:sanctum')->prefix('mission-and-vision')->group(function () {
    Route::get('/admin', [MissionAndVisionController::class, 'show']);
    Route::post('/', [MissionAndVisionController::class, 'store']);
    Route::put('/', [MissionAndVisionController::class, 'update']);
    Route::delete('/', [MissionAndVisionController::class, 'destroy']);
});

// Protected objectives routes - require authentication
Route::middleware('auth:sanctum')->prefix('objectives')->group(function () {
    Route::get('/', [ObjectiveController::class, 'show']);
    Route::post('/', [ObjectiveController::class, 'store']);
    Route::put('/', [ObjectiveController::class, 'update']);
    Route::delete('/', [ObjectiveController::class, 'destroy']);
});

// Protected office-contact routes - require authentication
Route::middleware('auth:sanctum')->prefix('office-contact')->group(function () {
    Route::get('/', [OfficeContactController::class, 'show']);
    Route::post('/', [OfficeContactController::class, 'store']);
    Route::put('/', [OfficeContactController::class, 'update']);
    Route::delete('/', [OfficeContactController::class, 'destroy']);
});