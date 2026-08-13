<?php

namespace App\Services\DocumentPipeline;

use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Finding;

class RuleEngineService
{
    public function run(CaseRecord $case): void
    {
        $rules = config("rules.{$case->vertical}", []);
        $context = new RuleCheckContext($case);

        // Re-running rule checks (e.g. a second document arrives, or the job is
        // retried) must not pile up duplicate findings. Unresolved findings are
        // a fresh re-evaluation each time; resolved ones stay as an audit trail.
        $case->findings()->whereNull('resolved_at')->delete();

        foreach ($rules as $ruleKey => $rule) {
            if (! ($rule['check'])($context)) {
                continue;
            }

            Finding::create([
                'case_id' => $case->id,
                'rule_key' => $ruleKey,
                'severity' => $rule['severity'],
                'message' => $rule['message'],
                'evidence' => ['rule_key' => $ruleKey],
            ]);
        }

        AuditLog::record($case->id, 'system', null, 'rule_check_completed', [
            'vertical' => $case->vertical,
            'findings_count' => $case->findings()->count(),
        ]);
    }
}
