<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentPipelineSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_runs_ingest_chunk_and_rule_engine_without_an_llm_key(): void
    {
        // OPENAI_API_KEY is unset in the testing environment (phpunit.xml), so this
        // exercises the ingest -> chunk -> classify(quick-path) -> rule-check chain
        // and confirms the pipeline stops cleanly at awaiting_api_key instead of
        // crashing the (sync, in this test) queue worker.
        Storage::fake('local');

        $user = User::factory()->create();

        $fixture = file_get_contents(base_path('tests/Fixtures/sample_payslip.txt'));
        $file = UploadedFile::fake()->createWithContent('sample_payslip.txt', $fixture);

        $response = $this->actingAs($user)->post(route('cases.store'), [
            'vertical' => 'financial_evidence',
            'reference' => 'SMOKE-TEST-1',
            'document' => $file,
        ]);

        $case = CaseRecord::firstOrFail();
        $response->assertRedirect(route('cases.show', $case));

        $document = $case->documents()->firstOrFail();

        // Quick-path keyword classify should hit on "payslip" / "gross pay" / "net pay".
        $this->assertSame('payslip', $document->doc_type);

        // Extract stage has no API key to call, so the chain parks here cleanly.
        $this->assertSame('awaiting_api_key', $document->fresh()->pipeline_status);
        $this->assertSame('awaiting_api_key', $case->fresh()->status);

        $this->assertGreaterThan(0, $case->documents()->firstOrFail()->chunks()->count());

        $this->assertDatabaseHas('audit_log', [
            'case_id' => $case->id,
            'action' => 'document_ingested',
        ]);
        $this->assertDatabaseHas('audit_log', [
            'case_id' => $case->id,
            'action' => 'llm_call_skipped_no_key',
        ]);

        // Rule engine ran even without an LLM key: applicant_name/document_date
        // weren't LLM-extracted, so the blocker findings should be present.
        $this->assertDatabaseHas('findings', [
            'case_id' => $case->id,
            'rule_key' => 'missing_applicant_name',
        ]);
    }
}
