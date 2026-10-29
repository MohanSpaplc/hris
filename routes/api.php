<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\CandidateDocumentController;
use App\Http\Controllers\Api\CandidateResumeController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientLogoController;
use App\Http\Controllers\Api\ClientManagerController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\JobDescriptionController;
use App\Http\Controllers\Api\JobFormOptionsController;
use App\Http\Controllers\Api\PracticeAreaController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VendorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public (guest) endpoints, each with its own brute-force limiter.
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');
        Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:password-reset')->name('password.email');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset')->name('password.update');
    });

    // Authenticated endpoints.
    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
        Route::prefix('auth')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('auth.me');
            Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
            Route::post('change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');
        });

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::apiResource('users', UserController::class);

        Route::get('statuses', [StatusController::class, 'index'])->name('statuses.index');

        // Masters
        Route::apiResource('countries', CountryController::class);
        Route::apiResource('practice-areas', PracticeAreaController::class);
        Route::apiResource('clients', ClientController::class);
        Route::post('clients/{client}/logo', [ClientLogoController::class, 'store'])->name('clients.logo.store');
        Route::delete('clients/{client}/logo', [ClientLogoController::class, 'destroy'])->name('clients.logo.destroy');
        Route::apiResource('client-managers', ClientManagerController::class);
        Route::apiResource('vendors', VendorController::class);

        // Jobs
        Route::get('skills', [SkillController::class, 'index'])->name('skills.index');
        Route::get('jobs/form-options', JobFormOptionsController::class)->name('jobs.form-options');
        Route::post('jobs/parse-jd', [JobDescriptionController::class, 'parse'])->middleware('throttle:20,1')->name('jobs.parse-jd');
        Route::apiResource('jobs', JobController::class);
        Route::post('jobs/{job}/jd-file', [JobDescriptionController::class, 'upload'])->name('jobs.jd-file.store');
        Route::get('jobs/{job}/jd-file', [JobDescriptionController::class, 'download'])->name('jobs.jd-file.show');
        Route::delete('jobs/{job}/jd-file', [JobDescriptionController::class, 'destroy'])->name('jobs.jd-file.destroy');

        // Candidates
        Route::post('candidates/parse-resume', [CandidateResumeController::class, 'parse'])->middleware('throttle:20,1')->name('candidates.parse-resume');
        Route::apiResource('candidates', CandidateController::class);
        Route::apiResource('candidates.documents', CandidateDocumentController::class)->scoped();
    });
});
