<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScenarioController;
use Illuminate\Support\Facades\Route;

Route::get(
    '/',
    fn() => auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login')
);

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

// Profile routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Read-only routes
Route::middleware('auth')->group(function () {
    Route::get('/scenarios', [ScenarioController::class, 'index'])->name('scenarios.index');
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::get('/conversations/{conversation}/export', [ConversationController::class, 'export'])
        ->name('conversations.export');
});

// Write routes — rate limited to protect Groq API
Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::post('/conversations/{conversation}/complete', [ConversationController::class, 'complete'])->name('conversations.complete');
});

require __DIR__ . '/auth.php';
