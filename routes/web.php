<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\QuizController as AdminQuizController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VideoController as AdminVideoController;
use App\Http\Controllers\HomeDashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Portal\PortalProfileController;
use App\Http\Controllers\Portal\QuizSubmitController;
use App\Http\Controllers\Portal\VideoResumeController;
use App\Http\Controllers\Portal\VideoWatchController;
use Illuminate\Support\Facades\Route;

// ─── Public Landing Page ─────────────────────────────────────────────────────
Route::get('/', LandingController::class)->name('home');

// ─── Authenticated User Routes ────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {

    // Redirect /dashboard → role-based destination
    Route::get('/dashboard', function () {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('portal.home');
    })->name('dashboard');

    // User portal home
    Route::get('/home', HomeDashboardController::class)->name('portal.home');

    // Video watch & resume
    Route::get('/video/{video}', [VideoWatchController::class, 'show'])->name('portal.video.watch');
    Route::post('/video/{video}/resume', [VideoResumeController::class, 'store'])->name('portal.video.resume');

    // Quiz submit
    Route::post('/video/{video}/quiz/submit', [QuizSubmitController::class, 'store'])->name('portal.quiz.submit');

    // Profile
    Route::get('/profile', [PortalProfileController::class, 'show'])->name('portal.profile');
    Route::patch('/profile', [PortalProfileController::class, 'update'])->name('portal.profile.update');
});

// ─── Admin Routes ─────────────────────────────────────────────────────────────
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    // Video Management
    Route::get('/videos', [AdminVideoController::class, 'index'])->name('videos.index');
    Route::get('/videos/transcoding-status', [AdminVideoController::class, 'transcodingStatus'])->name('videos.transcoding-status');
    Route::post('/videos', [AdminVideoController::class, 'store'])->name('videos.store');
    Route::get('/videos/{video}', [AdminVideoController::class, 'show'])->name('videos.show');
    Route::patch('/videos/{video}', [AdminVideoController::class, 'update'])->name('videos.update');
    Route::patch('/videos/{video}/publish', [AdminVideoController::class, 'togglePublish'])->name('videos.publish');
    Route::post('/videos/{video}/generate-quiz', [AdminVideoController::class, 'generateQuiz'])->name('videos.generate-quiz');
    Route::delete('/videos/{video}', [AdminVideoController::class, 'destroy'])->name('videos.destroy');

    // Quiz Management
    Route::get('/quiz', [AdminQuizController::class, 'listAll'])->name('quiz.list');           // Sidebar overview
    Route::get('/videos/{video}/quiz', [AdminQuizController::class, 'index'])->name('videos.quiz.index');
    Route::post('/videos/{video}/quiz/generate', [AdminQuizController::class, 'generate'])->name('videos.quiz.generate');
    Route::post('/videos/{video}/quiz/questions', [AdminQuizController::class, 'storeQuestion'])->name('videos.quiz.questions.store');
    Route::patch('/questions/{question}', [AdminQuizController::class, 'updateQuestion'])->name('questions.update');
    Route::delete('/questions/{question}', [AdminQuizController::class, 'destroyQuestion'])->name('questions.destroy');

    // User Management
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/status', [AdminUserController::class, 'toggleStatus'])->name('users.status');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
});

require __DIR__.'/settings.php';
