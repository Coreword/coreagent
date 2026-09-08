<?php

namespace App\Services\WhatsApp;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Maps a WhatsApp phone number onto the same User/Conversation schema the web
 * chat uses, so AgentOrchestrator, AgentStep, and the tool trace all work
 * unmodified regardless of channel. A WhatsApp contact is not a portal
 * account — it never logs in — but conversations.user_id is a required,
 * constrained foreign key, so it needs a real (if unusable) User row rather
 * than a schema change to make that column nullable.
 */
class WhatsAppConversationResolver
{
    public function resolve(string $phoneNumber): Conversation
    {
        $user = User::firstOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'name' => "WhatsApp {$phoneNumber}",
                'email' => "wa-{$phoneNumber}@whatsapp.coreagent.invalid",
                // Unusable password: this account never logs in through
                // routes/auth.php, it's only ever loaded by id from the webhook.
                'password' => Hash::make(Str::random(40)),
            ],
        );

        // One continuous conversation per number — a WhatsApp thread isn't
        // sessioned the way a browser tab is, so there's no "+ New chat" here.
        return Conversation::firstOrCreate(
            ['user_id' => $user->id, 'channel' => 'whatsapp'],
            ['status' => 'idle'],
        );
    }
}
