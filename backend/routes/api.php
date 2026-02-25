<?php

use App\Http\Controllers\CompanyIntelligenceController;
use Illuminate\Support\Facades\Route;

// Grouping endpoints under a common prefix for good RESTful design
Route::prefix('companies')->group(function () {
    Route::post('/process', [CompanyIntelligenceController::class, 'process']);
    Route::get('/status', [CompanyIntelligenceController::class, 'status']);
    Route::get('/', [CompanyIntelligenceController::class, 'index']);
    Route::get('/{id}', [CompanyIntelligenceController::class, 'show']);
});