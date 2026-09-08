<?php

use App\Http\Controllers\CaseController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// coreAgent's home surface is the AI chat — '/' just canonicalises to '/chat'
// so the nav's "AI Chat" link highlights correctly (route().current('chat.*')).
Route::redirect('/', '/chat')->middleware('auth')->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
    Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
    Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show');
    Route::post('/cases/{case}/documents', [CaseController::class, 'addDocument'])->name('cases.documents.store');

    Route::get('/chat', [ConversationController::class, 'index'])->name('chat.index');
    Route::post('/chat', [ConversationController::class, 'store'])->name('chat.store');
    Route::get('/chat/{conversation}', [ConversationController::class, 'show'])->name('chat.show');
    Route::patch('/chat/{conversation}', [ConversationController::class, 'update'])->name('chat.update');
    Route::post('/chat/{conversation}/messages', [MessageController::class, 'store'])->name('chat.messages.store');
});

// Meta calls these directly — no 'auth' middleware, and the POST route is
// excepted from CSRF verification in bootstrap/app.php. Request authenticity
// is instead the X-Hub-Signature-256 check in WhatsAppWebhookController.
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:120,1')
    ->name('webhooks.whatsapp.receive');

require __DIR__.'/auth.php';
