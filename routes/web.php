<?php

use App\Http\Controllers\ClientNotificationController;
use App\Http\Controllers\ClientProjectController;
use App\Http\Controllers\ProfileController;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Models\SupportTicket;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function (Request $request) {
    $client = $request->user()->client;

    return Inertia::render('Dashboard', [
        'projectsCount' => $client?->projects()->count() ?? 0,
        'supportTicketsCount' => $client
            ? SupportTicket::whereHas('project', fn ($query) => $query->where('client_id', $client->id))->count()
            : 0,
        'projectUpdatesCount' => $client
            ? ProjectUpdate::whereHas('project', fn ($query) => $query->where('client_id', $client->id))->count()
            : 0,
        'filesCount' => $client
            ? ProjectFile::whereHas('project', fn ($query) => $query->where('client_id', $client->id))->count()
            : 0,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/notifications', [ClientNotificationController::class, 'index'])->name('client.notifications.index');
    Route::patch('/notifications/{notification}/read', [ClientNotificationController::class, 'markAsRead'])->name('client.notifications.read');
    Route::get('/projects', [ClientProjectController::class, 'index'])->name('client.projects.index');
    Route::get('/project-files/{projectFile}/download', [ClientProjectController::class, 'downloadFile'])->name('client.project-files.download');
    Route::get('/projects/{project}', [ClientProjectController::class, 'show'])->name('client.projects.show');
});

require __DIR__.'/auth.php';
