<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_handle_run($request) {
    $start = microtime(true);
    $params = ai_gateway_get_json_params($request);
    $agent_id = isset($params['agent_id']) ? absint($params['agent_id']) : 0;
    $instruction = isset($params['instruction']) ? sanitize_textarea_field($params['instruction']) : '';
    $inputs = isset($params['inputs']) && is_array($params['inputs']) ? array_map('sanitize_text_field', $params['inputs']) : [];

    $agent = ai_gateway_get_agent($agent_id);
    if (!$agent) {
        return ['error' => 'Agent introuvable.'];
    }

    $schema = $agent['input_schema'] ?? [];
    $inputs = ai_gateway_apply_schema_env_values($schema, $inputs);
    $log_inputs = ai_gateway_redact_env_inputs($schema, $inputs);

    $prompt_parts = [];
    if (!empty($agent['system_prompt'])) {
        $prompt_parts[] = $agent['system_prompt'];
    }
    $prompt_parts[] = 'Instruction: ' . $instruction;
    if (!empty($inputs)) {
        $prompt_parts[] = 'Inputs: ' . wp_json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    $prompt_parts[] = 'Reponds en texte brut, concis et executable.';
    $prompt = implode("\n\n", $prompt_parts);

    $ollama_response_text = '';
    $provider_result = ai_gateway_call_provider($agent, $instruction, $inputs);
    if (isset($provider_result['error']) && $provider_result['error'] !== 'use_ollama_handler') {
        ai_gateway_log_execution([
            'agent_id' => $agent['id'],
            'agent_name' => $agent['name'],
            'provider' => ai_gateway_get_provider_for_agent($agent),
            'model' => $agent['model'],
            'status' => 'error',
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            'error' => $provider_result['error'],
            'input_full' => [
                'instruction' => $instruction,
                'inputs' => $log_inputs,
            ],
            'output_full' => '',
            'output_mode' => $agent['output_mode'],
        ]);
        return ['error' => $provider_result['error']];
    }
    if (isset($provider_result['text'])) {
        $ollama_response_text = $provider_result['text'];
    } else {
        $ollama_url = ai_gateway_get_ollama_url();
        $ollama_payload = [
            'model' => $agent['model'] ?: 'mistral',
            'prompt' => $prompt,
            'stream' => false,
        ];
        $options = ai_gateway_get_ollama_options($agent);
        if (!empty($options)) {
            $ollama_payload['options'] = $options;
        }

    $ollama_response = wp_remote_post($ollama_url, [
        'timeout' => 120,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($ollama_payload),
        ]);

        if (is_wp_error($ollama_response)) {
            return ['error' => $ollama_response->get_error_message()];
        }

        $raw_body = wp_remote_retrieve_body($ollama_response);
        $ollama_body = json_decode($raw_body, true);
        if (ai_gateway_is_model_not_found($ollama_response, $ollama_body ?: $raw_body)) {
            ai_gateway_log_execution([
                'agent_id' => $agent['id'],
                'agent_name' => $agent['name'],
                'provider' => ai_gateway_get_provider_for_agent($agent),
                'model' => $agent['model'],
                'status' => 'error',
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'error' => 'model_not_found',
                'input_full' => [
                    'instruction' => $instruction,
                    'inputs' => $log_inputs,
                ],
                'output_full' => '',
                'output_mode' => $agent['output_mode'],
            ]);
            return [
                'error' => 'model_not_found',
                'model' => $agent['model'],
                'suggest_pull' => true,
            ];
        }
        if (is_array($ollama_body) && isset($ollama_body['response'])) {
            $ollama_response_text = $ollama_body['response'];
        }
    }

    $mcp_response_text = '';
    $mcp_meta = '';
    if (!empty($agent['mcp_endpoint'])) {
        $mcp_payload = [
            'agent_id' => $agent['id'],
            'instruction' => $instruction,
            'inputs' => $inputs,
            'ollama_response' => $ollama_response_text,
        ];

        $mcp_response = wp_remote_post(esc_url_raw($agent['mcp_endpoint']), [
            'timeout' => 120,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($mcp_payload),
        ]);

        if (is_wp_error($mcp_response)) {
            ai_gateway_log_execution([
                'agent_id' => $agent['id'],
                'agent_name' => $agent['name'],
                'provider' => ai_gateway_get_provider_for_agent($agent),
                'model' => $agent['model'],
                'status' => 'error',
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'error' => $mcp_response->get_error_message(),
                'input_full' => [
                    'instruction' => $instruction,
                    'inputs' => $log_inputs,
                ],
                'output_full' => $ollama_response_text,
                'output_mode' => $agent['output_mode'],
            ]);
            return ['error' => $mcp_response->get_error_message()];
        }

        $mcp_body = wp_remote_retrieve_body($mcp_response);
        $decoded = json_decode($mcp_body, true);
        if (is_array($decoded)) {
            if (isset($decoded['result'])) {
                $mcp_response_text = $decoded['result'];
            } else {
                $mcp_response_text = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        } else {
            $mcp_response_text = $mcp_body;
        }

        $mcp_meta = 'MCP: ' . $agent['mcp_endpoint'];
    }

    ai_gateway_log_execution([
        'agent_id' => $agent['id'],
        'agent_name' => $agent['name'],
        'provider' => ai_gateway_get_provider_for_agent($agent),
        'model' => $agent['model'],
        'status' => 'success',
        'duration_ms' => (int) ((microtime(true) - $start) * 1000),
        'error' => '',
        'input_full' => [
            'instruction' => $instruction,
            'inputs' => $log_inputs,
        ],
        'output_full' => $mcp_response_text ?: $ollama_response_text,
        'output_mode' => $agent['output_mode'],
    ]);

    return [
        'ollama_response' => $ollama_response_text,
        'mcp_response' => $mcp_response_text,
        'mcp_meta' => $mcp_meta,
        'output_mode' => $agent['output_mode'],
    ];
}
