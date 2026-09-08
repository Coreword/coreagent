<?php

namespace App\Http\Controllers;

use App\Jobs\RunAgentJob;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * CoreAI's landing surface: a single task composer rather than a
     * conversation picker. `prompt` arrives from Templates ("use this task").
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Home', [
            'prefill' => $request->query('prompt'),
        ]);
    }

    /**
     * One-shot like CoreAI's task submit: create the conversation, dispatch
     * the agent, and navigate straight to it — the composer here never shows
     * the reply inline. Mirrors MessageController::store, which is why it
     * doesn't create the user Message itself: AgentOrchestrator::run() does
     * that as its first step once the job picks it up.
     */
    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:8000',
        ]);

        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
            'status' => 'processing',
        ]);

        RunAgentJob::dispatch($conversation, $validated['content']);

        return redirect()->route('chat.show', $conversation);
    }
}
