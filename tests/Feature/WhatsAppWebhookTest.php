<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppReplyJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.whatsapp.verify_token' => 'test-verify-token']);
        config(['services.whatsapp.app_secret' => 'test-app-secret']);
    }

    public function test_verification_handshake_echoes_challenge_when_token_matches(): void
    {
        $response = $this->get('/webhooks/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'test-verify-token',
            'hub_challenge' => 'echo-me-12345',
        ]));

        $response->assertOk();
        $response->assertSee('echo-me-12345');
    }

    public function test_verification_handshake_rejects_wrong_token(): void
    {
        $response = $this->get('/webhooks/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong-token',
            'hub_challenge' => 'echo-me-12345',
        ]));

        $response->assertForbidden();
    }

    public function test_inbound_message_without_valid_signature_is_rejected(): void
    {
        $payload = $this->samplePayload('+85291234567', 'wamid.TEST1', '幾多個 case？');

        $response = $this->postJson('/webhooks/whatsapp', $payload, [
            'X-Hub-Signature-256' => 'sha256=deadbeef',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_inbound_text_message_creates_conversation_and_dispatches_agent_job(): void
    {
        Bus::fake([\App\Jobs\RunAgentJob::class]);

        $payload = $this->samplePayload('+85291234567', 'wamid.TEST2', '幾多個 case？');

        $response = $this->postJson('/webhooks/whatsapp', $payload, [
            'X-Hub-Signature-256' => $this->signaturePrefix().$this->sign($payload),
        ]);

        $response->assertOk();

        $user = User::where('phone_number', '+85291234567')->first();
        $this->assertNotNull($user, 'expected an auto-provisioned user for the WhatsApp contact');

        $conversation = Conversation::where('user_id', $user->id)->where('channel', 'whatsapp')->first();
        $this->assertNotNull($conversation);
        $this->assertSame('processing', $conversation->status);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => '幾多個 case？',
            'wa_message_id' => 'wamid.TEST2',
        ]);

        Bus::assertDispatched(\App\Jobs\RunAgentJob::class);
    }

    public function test_redelivered_message_id_is_not_processed_twice(): void
    {
        Bus::fake([\App\Jobs\RunAgentJob::class]);

        $payload = $this->samplePayload('+85291234567', 'wamid.DUPLICATE', 'hello');
        $signature = $this->signaturePrefix().$this->sign($payload);

        $this->postJson('/webhooks/whatsapp', $payload, ['X-Hub-Signature-256' => $signature])->assertOk();
        $this->postJson('/webhooks/whatsapp', $payload, ['X-Hub-Signature-256' => $signature])->assertOk();

        $this->assertSame(1, Message::where('wa_message_id', 'wamid.DUPLICATE')->count());
        Bus::assertDispatchedTimes(\App\Jobs\RunAgentJob::class, 1);
    }

    public function test_final_assistant_message_on_a_whatsapp_conversation_dispatches_the_reply_job(): void
    {
        Bus::fake([SendWhatsAppReplyJob::class]);

        $user = User::factory()->create(['phone_number' => '+85298765432']);
        $conversation = Conversation::create(['user_id' => $user->id, 'channel' => 'whatsapp', 'status' => 'processing']);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => '搵到 3 個 case。',
        ]);

        Bus::assertDispatched(SendWhatsAppReplyJob::class, fn ($job) => $job->message->is($message));
    }

    public function test_intermediate_tool_calling_message_does_not_dispatch_the_reply_job(): void
    {
        Bus::fake([SendWhatsAppReplyJob::class]);

        $user = User::factory()->create(['phone_number' => '+85298765432']);
        $conversation = Conversation::create(['user_id' => $user->id, 'channel' => 'whatsapp', 'status' => 'processing']);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [['id' => 'call_1', 'name' => 'list_cases', 'arguments' => []]],
        ]);

        Bus::assertNotDispatched(SendWhatsAppReplyJob::class);
    }

    protected function samplePayload(string $from, string $waMessageId, string $body): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'id' => $waMessageId,
                            'from' => $from,
                            'text' => ['body' => $body],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    protected function signaturePrefix(): string
    {
        return 'sha256=';
    }

    protected function sign(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), 'test-app-secret');
    }
}
