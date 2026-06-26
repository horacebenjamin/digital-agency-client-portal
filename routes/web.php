<?php

use App\Http\Controllers\ClientBillingController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\ClientNotificationController;
use App\Http\Controllers\ClientProjectController;
use App\Http\Controllers\ClientSupportTicketController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
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

Route::get('/dashboard', ClientDashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/billing', [ClientBillingController::class, 'index'])->name('client.billing.index');
    Route::post('/billing/payment-requests/{paymentRequest}/checkout', [ClientBillingController::class, 'checkout'])->name('client.billing.payment-requests.checkout');
    Route::get('/notifications', [ClientNotificationController::class, 'index'])->name('client.notifications.index');
    Route::patch('/notifications/{notification}/read', [ClientNotificationController::class, 'markAsRead'])->name('client.notifications.read');
    Route::get('/projects', [ClientProjectController::class, 'index'])->name('client.projects.index');
    Route::get('/project-files/{projectFile}/download', [ClientProjectController::class, 'downloadFile'])->name('client.project-files.download');
    Route::get('/projects/{project}', [ClientProjectController::class, 'show'])->name('client.projects.show');
    Route::get('/support-tickets', [ClientSupportTicketController::class, 'index'])->name('client.support-tickets.index');
    Route::get('/support-tickets/create', [ClientSupportTicketController::class, 'create'])->name('client.support-tickets.create');
    Route::post('/support-tickets', [ClientSupportTicketController::class, 'store'])->name('client.support-tickets.store');
    Route::get('/support-tickets/{supportTicket}', [ClientSupportTicketController::class, 'show'])->name('client.support-tickets.show');
    Route::post('/support-tickets/{supportTicket}/comments', [ClientSupportTicketController::class, 'storeComment'])->name('client.support-tickets.comments.store');
});

require __DIR__.'/auth.php';
