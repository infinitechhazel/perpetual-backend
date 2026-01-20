<?php

use App\Http\Controllers\Api\AmbulanceRequestController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarangayBlotterController;
use App\Http\Controllers\Api\BarangayClearanceController;
use App\Http\Controllers\Api\BuildingPermitController;
use App\Http\Controllers\Api\BusinessPartnerController;
use App\Http\Controllers\Api\BusinessPermitController;
use App\Http\Controllers\Api\CedulaController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\GoodMoralCertificateController;
use App\Http\Controllers\Api\HealthCertificateController;
use App\Http\Controllers\Api\IndigencyCertificateController;
use App\Http\Controllers\Api\LegitimacyController;
use App\Http\Controllers\Api\MedicalAssistanceController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ResidencyCertificateController;
use App\Http\Controllers\Api\SubscriberController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VlogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::patch('/users/{id}/status', [UserController::class, 'updateStatus']);
    Route::get('/users/statistics', [UserController::class, 'statistics']);
});
// ===================================
// APPLICATION ROUTES (All Protected)

// Members routes - user's own ambulance requests
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/emergency/ambulance', [AmbulanceRequestController::class, 'store']);
    Route::get('/emergency/ambulance', [AmbulanceRequestController::class, 'index']);
    Route::get('/emergency/ambulance/{id}', [AmbulanceRequestController::class, 'show']);
    Route::post('/emergency/ambulance/{id}/cancel', [AmbulanceRequestController::class, 'cancel']);
    Route::get('/admin/ambulance-requests', [AmbulanceRequestController::class, 'index']);
    Route::get('/admin/ambulance-requests/{id}', [AmbulanceRequestController::class, 'show']);
    Route::patch('/admin/ambulance-requests/{id}', [AmbulanceRequestController::class, 'update']);
});

// Admin routes - all ambulance requests

// ===================================
Route::middleware('auth:sanctum')->group(function () {

    // Barangay Clearance Routes
    Route::post('/barangay-clearance', [BarangayClearanceController::class, 'store']);
    Route::get('/barangay-clearance', [BarangayClearanceController::class, 'index']);
    Route::get('/barangay-clearance/{id}', [BarangayClearanceController::class, 'show']);
    Route::delete('/barangay-clearance/{id}', [BarangayClearanceController::class, 'destroy']);

    // Business Permit Routes - NOW PROPERLY PROTECTED!
    Route::post('/business-permit', [BusinessPermitController::class, 'store']);
    Route::get('/business-permit', [BusinessPermitController::class, 'index']);
    Route::get('/business-permit/{id}', [BusinessPermitController::class, 'show']);
    Route::patch('/business-permit/{id}', [BusinessPermitController::class, 'update']);
    Route::delete('/business-permit/{id}', [BusinessPermitController::class, 'destroy']);

    // Building Permit Routes
    Route::apiResource('building-permit', BuildingPermitController::class);

    // Cedula Routes
    Route::apiResource('cedula', CedulaController::class);

    // Report Routes
    Route::post('/reports/submit', [ReportController::class, 'submit']);
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{id}', [ReportController::class, 'show']);
});
Route::post('/contacts', [ContactController::class, 'store']);

// Protected routes (add your authentication middleware)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::get('/contacts/{id}', [ContactController::class, 'show']);
    Route::patch('/contacts/{id}/status', [ContactController::class, 'updateStatus']);
    Route::post('/admin/contacts/{id}/reply', [ContactController::class, 'reply']);
});
// ===================================
// ADMIN ONLY ROUTES - FIXED: Removed 'role:admin' middleware
// ===================================
Route::middleware(['auth:sanctum'])->group(function () {
    // Status updates
    Route::patch('/barangay-clearance/{id}/status', [BarangayClearanceController::class, 'updateStatus']);
    Route::patch('/business-permit/{id}/status', [BusinessPermitController::class, 'updateStatus']);
    Route::patch('/building-permit/{id}/status', [BuildingPermitController::class, 'updateStatus']);
    Route::patch('/cedula/{id}/status', [CedulaController::class, 'updateStatus']);

    // Admin-specific routes - NOW ACCESSIBLE!
    Route::get('/admin/business-permits', [BusinessPermitController::class, 'adminIndex']);
    Route::get('/admin/barangay-clearances', [BarangayClearanceController::class, 'adminIndex']);
    Route::get('/admin/building-permits', [BuildingPermitController::class, 'adminIndex']);
    Route::get('/admin/cedulas', [CedulaController::class, 'adminIndex']);
    Route::get('/admin/health-certificates', [HealthCertificateController::class, 'adminIndex']);

});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/medical-assistance', [MedicalAssistanceController::class, 'index']);
    Route::post('/medical-assistance', [MedicalAssistanceController::class, 'store']);
    Route::get('/medical-assistance/{id}', [MedicalAssistanceController::class, 'show']);
    Route::get('/medical-assistance/reference/{referenceNumber}', [MedicalAssistanceController::class, 'getByReferenceNumber']);
    Route::put('/medical-assistance/{id}', [MedicalAssistanceController::class, 'update']);
    Route::delete('/medical-assistance/{id}', [MedicalAssistanceController::class, 'destroy']);
    Route::get('/medical-assistance/statistics', [MedicalAssistanceController::class, 'statistics']);
    Route::get('/admin/medical-assistance', [MedicalAssistanceController::class, 'adminIndex']);
    Route::patch('/medical-assistance/{id}', [MedicalAssistanceController::class, 'updateStatus']);
});

Route::middleware('auth:sanctum')->prefix('health-certificate')->group(function () {
    Route::get('/', [HealthCertificateController::class, 'index']);
    Route::post('/', [HealthCertificateController::class, 'store']);
    Route::get('/statistics', [HealthCertificateController::class, 'statistics']);
    Route::get('/reference/{referenceNumber}', [HealthCertificateController::class, 'getByReferenceNumber']);
    Route::get('/{id}', [HealthCertificateController::class, 'show']);
    Route::put('/{id}', [HealthCertificateController::class, 'update']);
    Route::delete('/{id}', [HealthCertificateController::class, 'destroy']);

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
            Route::post('/{id}', [NewsController::class, 'update']); // POST for FormData
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
// Add this line to your routes/api.php
Route::get('/subscribers/active', [SubscriberController::class, 'getActiveSubscribers']);
Route::prefix('subscribers')->group(function () {
    Route::post('subscribe', [SubscriberController::class, 'subscribe']);
    Route::get('verify/{token}', [SubscriberController::class, 'verify']);
    Route::get('unsubscribe/{token}', [SubscriberController::class, 'unsubscribe']);

    // For Next.js to fetch subscribers for email sending
    Route::get('active', [SubscriberController::class, 'getActiveSubscribers']);

});

// Admin subscriber routes (protected)
Route::middleware(['auth:sanctum'])->prefix('admin/subscribers')->group(function () {
    Route::get('/', [SubscriberController::class, 'index']);
    Route::get('statistics', [SubscriberController::class, 'statistics']);
    Route::delete('{id}', [SubscriberController::class, 'destroy']);
});

// Certificate routes inside auth:sanctum middleware and removed 'admin' middleware
Route::middleware(['auth:sanctum'])->group(function () {

    // ============================================
    // INDIGENCY CERTIFICATE ROUTES
    // ============================================

    // Members routes
    Route::post('/indigency-certificate', [IndigencyCertificateController::class, 'store']);
    Route::get('/indigency-certificate/my-applications', [IndigencyCertificateController::class, 'myApplications']);
    Route::get('/indigency-certificate/{id}', [IndigencyCertificateController::class, 'show']);

    // Admin routes - FIXED: removed ['admin'] middleware
    Route::get('/admin/indigency-certificates', [IndigencyCertificateController::class, 'index']);
    Route::patch('/indigency-certificate/{id}/status', [IndigencyCertificateController::class, 'updateStatus']);
    Route::delete('/indigency-certificate/{id}', [IndigencyCertificateController::class, 'destroy']);
});

// Residency Certificate routes for Memberss
Route::middleware('auth:sanctum')->group(function () {
    // Members routes
    Route::get('/residency-certificate', [ResidencyCertificateController::class, 'index']);
    Route::post('/residency-certificate', [ResidencyCertificateController::class, 'store']);
    Route::get('/residency-certificate/{id}', [ResidencyCertificateController::class, 'show']);
    Route::delete('/residency-certificate/{id}', [ResidencyCertificateController::class, 'destroy']);

    // Admin routes - FIXED: removed ['admin'] middleware
    Route::get('/admin/residency-certificates', [ResidencyCertificateController::class, 'adminIndex']);
    Route::patch('/residency-certificate/{id}/status', [ResidencyCertificateController::class, 'updateStatus']);
});

// Good Moral Certificate routes for Memberss
Route::middleware('auth:sanctum')->group(function () {
    // Members routes
    Route::get('/good-moral-certificate', [GoodMoralCertificateController::class, 'index']);
    Route::post('/good-moral-certificate', [GoodMoralCertificateController::class, 'store']);
    Route::get('/good-moral-certificate/{id}', [GoodMoralCertificateController::class, 'show']);
    Route::delete('/good-moral-certificate/{id}', [GoodMoralCertificateController::class, 'destroy']);

    // Admin routes - FIXED: removed ['admin'] middleware
    Route::get('/admin/good-moral-certificates', [GoodMoralCertificateController::class, 'adminIndex']);
    Route::patch('/good-moral-certificate/{id}/status', [GoodMoralCertificateController::class, 'updateStatus']);
});

// Barangay Blotter routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/barangay-blotter', [BarangayBlotterController::class, 'index']);
    Route::post('/barangay-blotter', [BarangayBlotterController::class, 'store']);
    Route::get('/barangay-blotter/{blotter}', [BarangayBlotterController::class, 'show']);
    Route::put('/barangay-blotter/{blotter}', [BarangayBlotterController::class, 'update']);
    Route::delete('/barangay-blotter/{blotter}', [BarangayBlotterController::class, 'destroy']);

    // Admin only routes
    Route::get('/admin/barangay-blotters', [BarangayBlotterController::class, 'adminIndex']);
});

Route::middleware('auth:sanctum')->group(function () {
    // member legitimacy request routes
    Route::get('legitimacy', [LegitimacyController::class, 'index']);
    Route::post('legitimacy', [LegitimacyController::class, 'store']);

    // Admin legitimacy request routes
    Route::get('admin/legitimacy', [LegitimacyController::class, 'adminIndex']);
    Route::post('admin/legitimacy', [LegitimacyController::class, 'adminStore']);
    Route::put('admin/legitimacy/{id}', [LegitimacyController::class, 'adminUpdate']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Member routes for business partners
    Route::get('business-partners', [BusinessPartnerController::class, 'index']);
    Route::post('business-partners', [BusinessPartnerController::class, 'store']);

    // Admin routes for business partners
    Route::get('admin/business-partners', [BusinessPartnerController::class, 'adminIndex']);
    Route::put('admin/business-partners/{id}', [BusinessPartnerController::class, 'adminUpdate']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    // public
    Route::get('vlogs', [VlogController::class, 'index']);

    // admin
    Route::get('admin/vlogs', [VlogController::class, 'adminIndex']);
    Route::post('admin/vlogs', [VlogController::class, 'store']);
    Route::patch('admin/vlogs/{id}', [VlogController::class, 'update']);
    Route::delete('admin/vlogs/{id}', [VlogController::class, 'destroy']);
});
