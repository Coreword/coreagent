<?php

namespace App\Http\Controllers;

use App\Models\CaseRecord;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    /**
     * Real recent activity, not a mock feed: the user's own messages plus
     * every case's creation, merged by time. There's no dedicated audit
     * surface yet, so this reads straight off the two tables that already
     * carry a timeline.
     */
    public function index(Request $request): Response
    {
        $conversationIds = Conversation::where('user_id', $request->user()->id)->pluck('id');

        $messages = Message::whereIn('conversation_id', $conversationIds)
            ->where('role', 'user')
            ->latest('created_at')
            ->limit(20)
            ->get(['id', 'conversation_id', 'content', 'created_at'])
            ->map(fn (Message $m) => [
                'type' => 'message',
                'icon' => 'blue',
                'title' => Str::limit($m->content, 90),
                'subtitle' => 'AI Chat',
                'at' => $m->created_at,
                'href' => route('chat.show', $m->conversation_id),
            ]);

        $cases = CaseRecord::latest('created_at')
            ->limit(20)
            ->get(['id', 'vertical', 'reference', 'status', 'created_at'])
            ->map(fn (CaseRecord $c) => [
                'type' => 'case',
                'icon' => 'orange',
                'title' => $c->reference ?? "Case #{$c->id}",
                'subtitle' => "{$c->vertical} · {$c->status}",
                'at' => $c->created_at,
                'href' => route('cases.show', $c->id),
            ]);

        $items = $messages->concat($cases)
            ->sortByDesc('at')
            ->take(30)
            ->values();

        return Inertia::render('ActivityPage', [
            'items' => $items,
            'stats' => [
                'messages' => Message::whereIn('conversation_id', $conversationIds)->where('role', 'user')->count(),
                'cases' => CaseRecord::count(),
                'conversations' => $conversationIds->count(),
            ],
        ]);
    }
}
