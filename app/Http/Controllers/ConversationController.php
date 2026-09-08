<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\Llm\ProviderRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render($request, null);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorizeConversation($request, $conversation);

        return $this->render($request, $conversation);
    }

    public function store(Request $request): RedirectResponse
    {
        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
            'status' => 'idle',
        ]);

        return redirect()->route('chat.show', $conversation);
    }

    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeConversation($request, $conversation);

        $validated = $request->validate([
            'provider' => 'nullable|string|in:openai,deepseek,claude,qwen_lora',
        ]);

        $conversation->update(['provider' => $validated['provider'] ?? null]);

        return redirect()->route('chat.show', $conversation);
    }

    protected function render(Request $request, ?Conversation $active): Response
    {
        return Inertia::render('Agent/Chat', [
            'conversations' => Conversation::where('user_id', $request->user()->id)
                ->latest('updated_at')
                ->get(['id', 'title', 'status', 'provider', 'updated_at']),
            'activeConversation' => $active?->load([
                'messages' => fn ($q) => $q->where('role', '!=', 'tool'),
                'steps',
                'videoGenerations' => fn ($q) => $q->latest(),
            ]),
            'availableProviders' => app(ProviderRouter::class)->availableProviders(),
        ]);
    }

    protected function authorizeConversation(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);
    }
}
