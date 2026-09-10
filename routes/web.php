<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TemplatesController;
use App\Http\Controllers\WhatsAppAgentController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// coreAgent's landing surface is the CoreAI-style task composer, not a
// conversation picker — '/' canonicalises to '/home' so the nav's "Home"
// link highlights correctly (route().current('home')).
Route::redirect('/', '/home')->name('root');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// /home is the one page a guest can actually see: the full sidebar/composer
// shell renders, but AuthenticatedLayout auto-opens a login modal over it and
// Home.vue gates every real action (send, New Agent, start cards) behind it.
// Submitting a task still requires a session — 'home.submit' stays gated.
Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::middleware('auth')->group(function () {
    Route::post('/home', [HomeController::class, 'submit'])->name('home.submit');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // "Projects" in the nav — CoreAI's project entity maps onto coreAgent's
    // real Document Copilot cases. Route names stay cases.* since the
    // controller/tests predate this pass; only the UI labels changed.
    Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
    Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
    Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show');
    Route::post('/cases/{case}/documents', [CaseController::class, 'addDocument'])->name('cases.documents.store');

    Route::get('/chat', [ConversationController::class, 'index'])->name('chat.index');
    Route::post('/chat', [ConversationController::class, 'store'])->name('chat.store');
    Route::get('/chat/{conversation}', [ConversationController::class, 'show'])->name('chat.show');
    Route::patch('/chat/{conversation}', [ConversationController::class, 'update'])->name('chat.update');
    Route::post('/chat/{conversation}/messages', [MessageController::class, 'store'])->name('chat.messages.store');

    Route::get('/whatsapp-agents', [WhatsAppAgentController::class, 'index'])->name('whatsapp-agents');
    Route::get('/templates', [TemplatesController::class, 'index'])->name('templates');
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity');

    // Scheduled and Integrations have no backend yet — honest "coming soon"
    // shells in CoreAI's own layout, not sample data standing in for a
    // feature that doesn't exist (see the "coming soon" nav-scope decision).
    Route::get('/scheduled', fn () => Inertia::render('Scheduled'))->name('scheduled');
    Route::get('/integrations', fn () => Inertia::render('Integrations', [
        'whatsappConfigured' => filled(config('services.whatsapp.access_token')),
    ]))->name('integrations');
});

// Meta calls these directly — no 'auth' middleware, and the POST route is
// excepted from CSRF verification in bootstrap/app.php. Request authenticity
// is instead the X-Hub-Signature-256 check in WhatsAppWebhookController.
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:120,1')
    ->name('webhooks.whatsapp.receive');

require __DIR__.'/auth.php';
