<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Langflow Flow B — Duplicate Intelligence client.
 */
class LangflowDuplicateClient
{
    private string $baseUrl;
    private string $flowId;
    private string $apiKey;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl  = rtrim((string) config('langflow.base_url', ''), '/');
        $this->flowId   = (string) config('langflow.duplicate_flow_id', '');
        $this->apiKey   = (string) config('langflow.api_key', '');
        $this->timeout  = (int) config('langflow.timeout_seconds', 45);
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->flowId);
    }

    /**
     * @throws \RuntimeException on unrecoverable failure
     */
    public function run(array $payload): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('LANGFLOW_DUPLICATE_FLOW_ID or LANGFLOW_BASE_URL not configured.');
        }

        $url     = "{$this->baseUrl}/api/v1/run/{$this->flowId}";
        $headers = ['Content-Type' => 'application/json'];
        if (!empty($this->apiKey)) {
            $headers['x-api-key'] = $this->apiKey;
        }

        $response = Http::withHeaders($headers)
            ->timeout($this->timeout)
            ->post($url, ['input_value' => json_encode($payload), 'input_type' => 'json', 'output_type' => 'json']);

        if (!$response->successful()) {
            Log::warning('Langflow DuplicateIntelligence HTTP error', [
                'status'  => $response->status(),
                'flow_id' => $this->flowId,
            ]);
            throw new \RuntimeException('Langflow DuplicateIntelligence returned HTTP ' . $response->status());
        }

        return $response->json() ?? [];
    }
}
