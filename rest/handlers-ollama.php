<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_rest_pull_model_stream($request) {
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Unauthorized', ['status' => 403]);
    }

    $params = ai_gateway_get_json_params($request);
    $model = isset($params['model']) ? sanitize_text_field($params['model']) : '';
    if (!$model) {
        return new WP_Error('invalid', 'Model required', ['status' => 400]);
    }

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

    $ollama_url = str_replace('/api/generate', '/api/pull', ai_gateway_get_ollama_url());
    $payload = [
        'name' => $model,
        'stream' => true,
    ];

    $buffer = '';
    $ch = curl_init($ollama_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$buffer) {
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
            echo 'data: ' . wp_json_encode($decoded) . "\n\n";
            flush();
        }
        return strlen($data);
    });

    $result = curl_exec($ch);
    if ($result === false) {
        echo 'data: ' . wp_json_encode(['error' => curl_error($ch)]) . "\n\n";
        flush();
    }
    curl_close($ch);

    echo 'data: ' . wp_json_encode(['done' => true]) . "\n\n";
    flush();
    exit;
}
