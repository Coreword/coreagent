<?php

namespace App\Http\Controllers;

use App\Jobs\RunAgentJob;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'content' => 'required|string|max:8000',
        ]);

        // Set optimistically so the frontend poll sees "processing" immediately,
        // rather than racing the queue worker picking the job up.
        $conversation->update(['status' => 'processing']);

        RunAgentJob::dispatch($conversation, $validated['content']);

        return redirect()->route('chat.show', $conversation);
    }
}
