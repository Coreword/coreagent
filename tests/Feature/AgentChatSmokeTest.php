<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentChatSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_a_message_without_any_provider_key_degrades_gracefully(): void
    {
        // No OPENAI_API_KEY/DEEPSEEK_API_KEY/ANTHROPIC_API_KEY set in the testing
        // environment (phpunit.xml), so ProviderRouter has nothing to try. The
        // agent loop must still resolve cleanly instead of crashing the (sync,
        // in this test) queue worker — mirrors the awaiting_api_key pattern from
        // the Document Copilot pipeline.
        $user = User::factory()->create();

        $storeResponse = $this->actingAs($user)->post(route('chat.store'));
        $conversation = Conversation::firstOrFail();
        $storeResponse->assertRedirect(route('chat.show', $conversation));

        $messageResponse = $this->actingAs($user)->post(
            route('chat.messages.store', $conversation),
            ['content' => '幫我睇下有幾多個 case']
        );
        $messageResponse->assertRedirect(route('chat.show', $conversation));

        $conversation->refresh();
        $this->assertSame('idle', $conversation->status);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => '幫我睇下有幾多個 case',
        ]);

        $assistantMessage = $conversation->messages()->where('role', 'assistant')->first();
        $this->assertNotNull($assistantMessage);
        $this->assertStringContainsString('未設定任何 LLM provider', $assistantMessage->content);
    }

    public function test_conversation_is_scoped_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $owner->id, 'status' => 'idle']);

        $this->actingAs($intruder)->get(route('chat.show', $conversation))->assertForbidden();
    }
}
