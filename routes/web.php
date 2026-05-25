<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\VisitsController;

Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'DHL Backend API is running',
        'version' => app()->version(),
        'api_base' => '/api'
    ]);
});

// Middleware validation endpoint (not under /api prefix to avoid bypass)
Route::get('/validate/{uniqueid}', function (Request $request, $uniqueid) {
    // This route will trigger ClientAuth and ReferrerAuth middlewares
    return response()->json([
        'success' => true,
        'message' => 'Access granted',
        'uniqueid' => $uniqueid,
        'timestamp' => now()
    ]);
})->middleware(['set.uniqueid', 'referrer.auth', 'client.auth']);

// Visits routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/visits', [VisitsController::class, 'index'])->name('visits.index');
    Route::post('/visits', [VisitsController::class, 'store'])->name('visits.store');
    Route::get('/visits/{visit}', [VisitsController::class, 'show'])->name('visits.show');
    Route::put('/visits/{visit}', [VisitsController::class, 'update'])->name('visits.update');
    Route::delete('/visits/{visit}', [VisitsController::class, 'destroy'])->name('visits.destroy');
    Route::post('/visits/bulk/destroy', [VisitsController::class, 'bulkDestroy'])->name('visits.bulk.destroy');
    Route::get('/visits/stats', [VisitsController::class, 'stats'])->name('visits.stats');
});

require __DIR__.'/auth.php';
