<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LangflowClient
{
    private string $serverUrl;
    private string $flowId;
    private string $apiKey;
    private int $timeoutSeconds;

    public function __construct()
    {
        $this->serverUrl      = rtrim((string) config('langflow.server_url'), '/');
        $this->flowId         = (string) config('langflow.flow_id');
        $this->apiKey         = (string) config('langflow.api_key');
        $this->timeoutSeconds = (int) config('langflow.timeout_seconds', 30);
    }

    /**
     * Run the configured Langflow flow with $payload.
     * Returns the parsed response array or throws on unrecoverable failure.
     *
     * @throws \RuntimeException
     */
    public function run(array $payload): array
    {
        if (empty($this->serverUrl) || empty($this->flowId)) {
            throw new \RuntimeException('LANGFLOW_SERVER_URL or LANGFLOW_FLOW_ID not configured.');
        }

        $url = "{$this->serverUrl}/api/v1/run/{$this->flowId}";

        $headers = ['Content-Type' => 'application/json'];
        if (!empty($this->apiKey)) {
            $headers['x-api-key'] = $this->apiKey;
        }

        $response = Http::withHeaders($headers)
            ->timeout($this->timeoutSeconds)
            ->post($url, ['input_value' => json_encode($payload), 'input_type' => 'json', 'output_type' => 'json']);

        if (!$response->successful()) {
            Log::warning('Langflow HTTP error', [
                'status' => $response->status(),
                'flow_id' => $this->flowId,
            ]);
            throw new \RuntimeException('Langflow returned HTTP ' . $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * Normalize the raw Langflow response to a flat array matching ai_analyses fields.
     * Returns null when the response cannot be mapped to the expected schema.
     */
    public function normalize(array $raw): ?array
    {
        // Langflow v1 wraps output in outputs[0].outputs[0].results.message.data.text
        // or outputs[0].outputs[0].results.text. We attempt both.
        $text = data_get($raw, 'outputs.0.outputs.0.results.message.data.text')
            ?? data_get($raw, 'outputs.0.outputs.0.results.text')
            ?? data_get($raw, 'result');

        if ($text === null) {
            return null;
        }

        // The flow should return JSON inside the text field.
        $decoded = is_array($text) ? $text : json_decode($text, true);
        if (!is_array($decoded)) {
            return null;
        }

        return [
            'flow_version'             => data_get($raw, 'session_id', 'unknown'),
            'provider'                 => data_get($decoded, 'provider'),
            'model_id'                 => data_get($decoded, 'model_id'),
            'category_suggested'       => data_get($decoded, 'category_suggested'),
            'summary'                  => data_get($decoded, 'summary'),
            'keywords_json'            => data_get($decoded, 'keywords', []),
            'urgency_suggestion'       => data_get($decoded, 'urgency_suggestion'),
            'confidence_band'          => data_get($decoded, 'confidence_band'),
            'related_report_ids_json'  => data_get($decoded, 'related_report_ids', []),
            'pattern_insight'          => data_get($decoded, 'pattern_insight'),
            'recommended_next_step'    => data_get($decoded, 'recommended_next_step'),
            'missing_information_json' => data_get($decoded, 'missing_information', []),
            'needs_human_review'       => (bool) data_get($decoded, 'needs_human_review', false),
        ];
    }
}
