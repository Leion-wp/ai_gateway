<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_handle_run_stream($request) {
    $start = microtime(true);
    $params = ai_gateway_get_json_params($request);
    $agent_id = isset($params['agent_id']) ? absint($params['agent_id']) : 0;
    $instruction = isset($params['instruction']) ? sanitize_textarea_field($params['instruction']) : '';
    $inputs = isset($params['inputs']) && is_array($params['inputs']) ? array_map('sanitize_text_field', $params['inputs']) : [];

    $agent = ai_gateway_get_agent($agent_id);
    if (!$agent) {
        return new WP_Error('not_found', 'Agent introuvable.', ['status' => 404]);
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

    nocache_headers();
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('X-Accel-Buffering: no');

    @ini_set('zlib.output_compression', 0);
    @ini_set('output_buffering', 'off');
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    flush();

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
        echo 'data: ' . wp_json_encode(['error' => $provider_result['error']]) . "\n\n";
        flush();
        exit;
    }
    if (isset($provider_result['text'])) {
        $mcp_response_text = '';
        $mcp_meta = '';
        if (!empty($agent['mcp_endpoint'])) {
            $mcp_payload = [
                'agent_id' => $agent['id'],
                'instruction' => $instruction,
                'inputs' => $inputs,
                'ollama_response' => $provider_result['text'],
            ];

            $mcp_response = wp_remote_post(esc_url_raw($agent['mcp_endpoint']), [
                'timeout' => 20,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($mcp_payload),
            ]);

            if (!is_wp_error($mcp_response)) {
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
            'output_full' => $mcp_response_text ?: $provider_result['text'],
            'output_mode' => $agent['output_mode'],
        ]);

        echo 'data: ' . wp_json_encode([
            'done' => true,
            'full' => $provider_result['text'],
            'mcp_response' => $mcp_response_text,
            'mcp_meta' => $mcp_meta,
            'output_mode' => $agent['output_mode'],
        ]) . "\n\n";
        flush();
        exit;
    }

    $ollama_url = ai_gateway_get_ollama_url();
    $ollama_payload = [
        'model' => $agent['model'] ?: 'mistral',
        'prompt' => $prompt,
        'stream' => true,
    ];
    $options = ai_gateway_get_ollama_options($agent);
    if (!empty($options)) {
        $ollama_payload['options'] = $options;
    }

    $full_text = '';
    $buffer = '';
    $done = false;

    $ch = curl_init($ollama_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($ollama_payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$buffer, &$full_text, &$done) {
        $buffer .= $data;
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = trim(substr($buffer, 0, $pos));
            $buffer = substr($buffer, $pos + 1);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }
            if (isset($decoded['response'])) {
                $delta = $decoded['response'];
                $full_text .= $delta;
                echo 'data: ' . wp_json_encode(['delta' => $delta]) . "\n\n";
                flush();
            }
            if (!empty($decoded['done'])) {
                $done = true;
            }
        }
        return strlen($data);
    });

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($result === false) {
        $error = curl_error($ch);
        ai_gateway_log_execution([
            'agent_id' => $agent['id'],
            'agent_name' => $agent['name'],
            'provider' => ai_gateway_get_provider_for_agent($agent),
            'model' => $agent['model'],
            'status' => 'error',
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            'error' => $error,
            'input_full' => [
                'instruction' => $instruction,
                'inputs' => $log_inputs,
            ],
            'output_full' => '',
            'output_mode' => $agent['output_mode'],
        ]);
        echo 'data: ' . wp_json_encode(['error' => $error]) . "\n\n";
        flush();
    }
    curl_close($ch);

    $mcp_response_text = '';
    $mcp_meta = '';
    if (!empty($agent['mcp_endpoint'])) {
        $mcp_payload = [
            'agent_id' => $agent['id'],
            'instruction' => $instruction,
            'inputs' => $inputs,
            'ollama_response' => $full_text,
        ];

        $mcp_response = wp_remote_post(esc_url_raw($agent['mcp_endpoint']), [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($mcp_payload),
        ]);

        if (!is_wp_error($mcp_response)) {
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
    }

    if ($http_code === 404) {
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
        echo 'data: ' . wp_json_encode([
            'error' => 'model_not_found',
            'model' => $agent['model'],
            'suggest_pull' => true,
        ]) . "\n\n";
        flush();
        exit;
    }

    if (!$done && $full_text === '') {
        ai_gateway_log_execution([
            'agent_id' => $agent['id'],
            'agent_name' => $agent['name'],
            'provider' => ai_gateway_get_provider_for_agent($agent),
            'model' => $agent['model'],
            'status' => 'error',
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            'error' => 'Streaming failed',
            'input_full' => [
                'instruction' => $instruction,
                'inputs' => $log_inputs,
            ],
            'output_full' => '',
            'output_mode' => $agent['output_mode'],
        ]);
        echo 'data: ' . wp_json_encode(['error' => 'Streaming failed']) . "\n\n";
        flush();
        exit;
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
        'output_full' => $mcp_response_text ?: $full_text,
        'output_mode' => $agent['output_mode'],
    ]);

    echo 'data: ' . wp_json_encode([
        'done' => true,
        'full' => $full_text,
        'mcp_response' => $mcp_response_text,
        'mcp_meta' => $mcp_meta,
        'output_mode' => $agent['output_mode'],
    ]) . "\n\n";
    flush();
    exit;
}
