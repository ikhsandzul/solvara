<?php

return [
    'base_url'             => env('LANGFLOW_BASE_URL'),
    'api_key'              => env('LANGFLOW_API_KEY'),
    'image_gate_flow_id'   => env('LANGFLOW_IMAGE_GATE_FLOW_ID'),
    'duplicate_flow_id'    => env('LANGFLOW_DUPLICATE_FLOW_ID'),
    'timeout_seconds'      => env('LANGFLOW_TIMEOUT_SECONDS', 45),
    'max_retries'          => env('LANGFLOW_MAX_RETRIES', 3),
    'connect_timeout_seconds' => env('LANGFLOW_CONNECT_TIMEOUT_SECONDS', 5),

    // v1 compatibility alias
    'server_url'  => env('LANGFLOW_BASE_URL'),
    'flow_id'     => env('LANGFLOW_IMAGE_GATE_FLOW_ID'),
];
