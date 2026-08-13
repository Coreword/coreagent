<?php

namespace App\Services\DocumentPipeline;

use App\Models\CaseRecord;
use App\Models\Extraction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RuleCheckContext
{
    /** @var Collection<string, Extraction> keyed by field_key, latest extraction per field */
    protected Collection $extractions;

    public function __construct(protected CaseRecord $case)
    {
        $this->extractions = $case->extractions()
            ->orderByDesc('id')
            ->get()
            ->unique('field_key')
            ->keyBy('field_key');
    }

    public function field(string $key): ?string
    {
        return $this->extractions->get($key)?->field_value;
    }

    public function date(string $key): ?Carbon
    {
        $value = $this->field($key);

        return $value ? Carbon::parse($value) : null;
    }

    public function confidence(string $key): ?float
    {
        $confidence = $this->extractions->get($key)?->confidence;

        return $confidence !== null ? (float) $confidence : null;
    }

    public function hasLowConfidenceField(float $threshold): bool
    {
        return $this->extractions->contains(
            fn (Extraction $extraction) => $extraction->confidence !== null && (float) $extraction->confidence < $threshold
        );
    }
}
