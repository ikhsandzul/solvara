<?php

namespace App\Services;

/**
 * Normalizes raw Langflow responses for Flow A and Flow B into structured arrays.
 */
class LangflowResponseNormalizer
{
    /**
     * Normalize Flow A (Image Gate) response.
     * Returns null if response cannot be mapped to expected schema.
     */
    public function normalizeImageGate(array $raw): ?array
    {
        $decoded = $this->extractOutputJson($raw);
        if ($decoded === null) {
            return null;
        }

        // Validate minimum required fields.
        if (!isset($decoded['moderation_decision'])) {
            return null;
        }

        $decision = $decoded['moderation_decision'];
        if (!in_array($decision, ['approved', 'rejected', 'manual_review'], true)) {
            return null;
        }

        return [
            'moderation_decision' => $decision,
            'is_civic_issue'      => (bool) data_get($decoded, 'is_civic_issue', false),
            'suggested_category'  => data_get($decoded, 'suggested_category'),
            'image_description'   => data_get($decoded, 'image_description'),
            'description_match'   => data_get($decoded, 'description_match'),
            'confidence_band'     => data_get($decoded, 'confidence_band'),
            'reasons'             => data_get($decoded, 'reasons', []),
            'needs_human_review'  => (bool) data_get($decoded, 'needs_human_review', false),
            'flow_version'        => data_get($decoded, 'flow_version'),
        ];
    }

    /**
     * Normalize Flow B (Duplicate Intelligence) response.
     * $allowedCandidateIds — IDs that were actually sent; hallucinates are stripped.
     */
    public function normalizeDuplicate(array $raw, array $allowedCandidateIds): ?array
    {
        $decoded = $this->extractOutputJson($raw);
        if ($decoded === null) {
            return null;
        }

        $decision = data_get($decoded, 'duplicate_decision');
        if (!in_array($decision, ['no_match', 'possible_duplicate', 'uncertain', 'recurring_issue'], true)) {
            return null;
        }

        // Validate and strip hallucinated candidate IDs.
        $rawMatches      = data_get($decoded, 'matches', []);
        $validatedMatches = [];
        foreach ($rawMatches as $m) {
            $cid = data_get($m, 'candidate_report_id');
            if ($cid && in_array((string) $cid, array_map('strval', $allowedCandidateIds), true)) {
                $validatedMatches[] = [
                    'candidate_report_id' => $cid,
                    'match_type'          => data_get($m, 'match_type'),
                    'confidence_band'     => data_get($m, 'confidence_band'),
                    'reason'              => data_get($m, 'reason'),
                ];
            }
        }

        return [
            'duplicate_decision' => $decision,
            'matches'            => $validatedMatches,
            'summary'            => data_get($decoded, 'summary'),
            'needs_human_review' => (bool) data_get($decoded, 'needs_human_review', false),
            'flow_version'       => data_get($decoded, 'flow_version'),
        ];
    }

    /**
     * Extract the JSON payload from a Langflow response envelope.
     * Tries known output paths from Langflow v1 API.
     */
    private function extractOutputJson(array $raw): ?array
    {
        $text = data_get($raw, 'outputs.0.outputs.0.results.message.data.text')
            ?? data_get($raw, 'outputs.0.outputs.0.results.text')
            ?? data_get($raw, 'result');

        if ($text === null) {
            return null;
        }

        if (is_array($text)) {
            return $text;
        }

        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }
}
