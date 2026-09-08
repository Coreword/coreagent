<?php

namespace Tests\Feature;

use App\Jobs\RunAgentJob;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))->assertOk();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_submitting_a_task_creates_a_conversation_and_dispatches_the_agent(): void
    {
        Bus::fake([RunAgentJob::class]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('home.submit'), [
            'content' => '幫我搵返 case reference "REF-1234"',
        ]);

        $conversation = Conversation::firstOrFail();
        $response->assertRedirect(route('chat.show', $conversation));

        $this->assertSame($user->id, $conversation->user_id);
        $this->assertSame('processing', $conversation->status);
        // The user Message itself is created inside AgentOrchestrator::run(),
        // not here — RunAgentJob is faked, so it should not exist yet.
        $this->assertDatabaseCount('messages', 0);

        // RunAgentJob's Conversation property is protected (see the job's
        // own docblock), so this checks count rather than reaching into it —
        // the conversation/redirect assertions above already pin identity.
        Bus::assertDispatchedTimes(RunAgentJob::class, 1);
    }

    public function test_blank_task_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('home.submit'), ['content' => ''])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('conversations', 0);
    }
}
