<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Langflow Flow A — Image Gate client.
 * Sends description + image reference; returns moderation decision.
 */
class LangflowImageGateClient
{
    private string $baseUrl;
    private string $flowId;
    private string $apiKey;
    private int    $timeout;
    private int    $maxRetries;

    public function __construct()
    {
        $this->baseUrl    = rtrim((string) config('langflow.base_url', ''), '/');
        $this->flowId     = (string) config('langflow.image_gate_flow_id', '');
        $this->apiKey     = (string) config('langflow.api_key', '');
        $this->timeout    = (int) config('langflow.timeout_seconds', 45);
        $this->maxRetries = (int) config('langflow.max_retries', 3);
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->flowId);
    }

    /**
     * Run the Image Gate flow.
     * @throws \RuntimeException on unrecoverable failure
     */
    public function run(array $payload): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('LANGFLOW_IMAGE_GATE_FLOW_ID or LANGFLOW_BASE_URL not configured.');
        }

        return $this->callFlow($this->flowId, $payload);
    }

    private function callFlow(string $flowId, array $payload): array
    {
        $url     = "{$this->baseUrl}/api/v1/run/{$flowId}";
        $headers = ['Content-Type' => 'application/json'];
        if (!empty($this->apiKey)) {
            $headers['x-api-key'] = $this->apiKey;
        }

        $response = Http::withHeaders($headers)
            ->timeout($this->timeout)
            ->post($url, ['input_value' => json_encode($payload), 'input_type' => 'json', 'output_type' => 'json']);

        if (!$response->successful()) {
            Log::warning('Langflow ImageGate HTTP error', [
                'status'  => $response->status(),
                'flow_id' => $flowId,
            ]);
            throw new \RuntimeException('Langflow ImageGate returned HTTP ' . $response->status());
        }

        return $response->json() ?? [];
    }
}
