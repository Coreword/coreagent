<?php

namespace App\Http\Middleware;

use App\Models\CaseRecord;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // The sidebar (AuthenticatedLayout.vue) renders on every
            // authenticated page — Home, Projects, Scheduled, etc. — not
            // just /chat, so its "Recent chats" and "Projects" tree data is
            // shared globally rather than threaded through every controller.
            // Closures so guest pages (login/register) skip the queries.
            'sidebar' => fn () => $request->user() ? [
                'recentChats' => Conversation::where('user_id', $request->user()->id)
                    ->where('channel', 'web')
                    ->latest('updated_at')
                    ->limit(8)
                    ->get(['id', 'title', 'status']),
                'recentCases' => CaseRecord::latest('created_at')
                    ->limit(6)
                    ->get(['id', 'vertical', 'reference']),
            ] : null,
        ];
    }
}
