<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_get_provider_list() {
    return [
        'ollama' => 'Ollama (local)',
        'openai' => 'OpenAI',
        'groq' => 'Groq',
        'openrouter' => 'OpenRouter',
        'anthropic' => 'Anthropic',
        'azure' => 'Azure OpenAI',
        'openai_compatible' => 'OpenAI-compatible',
    ];
}

function ai_gateway_get_provider_default() {
    $providers = ai_gateway_get_provider_list();
    $default = get_option('ai_gateway_provider_default', 'ollama');
    if (!isset($providers[$default])) {
        $default = 'ollama';
    }
    return $default;
}

function ai_gateway_get_provider_for_agent($agent) {
    if (is_array($agent) && !empty($agent['provider'])) {
        return $agent['provider'];
    }
    return ai_gateway_get_provider_default();
}

function ai_gateway_get_openai_compat_sources() {
    return [
        'openrouter' => [
            'label' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
        ],
        'lmstudio' => [
            'label' => 'LM Studio',
            'base_url' => 'http://localhost:1234/v1',
        ],
        'llama_cpp' => [
            'label' => 'llama.cpp',
            'base_url' => 'http://localhost:8080/v1',
        ],
        'vllm' => [
            'label' => 'vLLM',
            'base_url' => 'http://localhost:8000/v1',
        ],
        'custom' => [
            'label' => 'Custom',
            'base_url' => defined('AI_GATEWAY_CUSTOM_ENDPOINT') ? AI_GATEWAY_CUSTOM_ENDPOINT : '',
        ],
    ];
}

function ai_gateway_get_openai_compat_source_default() {
    $sources = ai_gateway_get_openai_compat_sources();
    $default = get_option('ai_gateway_openai_compat_source', 'openrouter');
    if (!isset($sources[$default])) {
        $default = 'openrouter';
    }
    return $default;
}

function ai_gateway_get_openai_compat_source_for_agent($agent) {
    if (is_array($agent) && !empty($agent['provider_source'])) {
        return $agent['provider_source'];
    }
    return ai_gateway_get_openai_compat_source_default();
}

function ai_gateway_get_openai_compat_base($source) {
    $sources = ai_gateway_get_openai_compat_sources();
    if (!isset($sources[$source])) {
        return '';
    }
    return $sources[$source]['base_url'] ?? '';
}

function ai_gateway_get_provider_key($provider, $source = '') {
    if ($provider === 'openai') {
        return defined('AI_GATEWAY_OPENAI_KEY') ? AI_GATEWAY_OPENAI_KEY : '';
    }
    if ($provider === 'groq') {
        return defined('AI_GATEWAY_GROQ_KEY') ? AI_GATEWAY_GROQ_KEY : '';
    }
    if ($provider === 'openrouter') {
        return defined('AI_GATEWAY_OPENROUTER_KEY') ? AI_GATEWAY_OPENROUTER_KEY : '';
    }
    if ($provider === 'anthropic') {
        return defined('AI_GATEWAY_ANTHROPIC_KEY') ? AI_GATEWAY_ANTHROPIC_KEY : '';
    }
    if ($provider === 'azure') {
        return defined('AI_GATEWAY_AZURE_KEY') ? AI_GATEWAY_AZURE_KEY : '';
    }
    if ($provider === 'openai_compatible') {
        if ($source === 'openrouter') {
            return defined('AI_GATEWAY_OPENROUTER_KEY') ? AI_GATEWAY_OPENROUTER_KEY : '';
        }
        if ($source === 'custom') {
            return defined('AI_GATEWAY_CUSTOM_KEY') ? AI_GATEWAY_CUSTOM_KEY : '';
        }
        return defined('AI_GATEWAY_OPENAI_COMPAT_KEY') ? AI_GATEWAY_OPENAI_COMPAT_KEY : '';
    }
    return '';
}

function ai_gateway_build_user_prompt($instruction, $inputs) {
    $parts = [];
    $parts[] = 'Instruction: ' . $instruction;
    if (!empty($inputs)) {
        $parts[] = 'Inputs: ' . wp_json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    return implode("\n\n", $parts);
}

function ai_gateway_call_openai_compatible($base_url, $api_key, $model, $system_prompt, $user_prompt, $options) {
    if (!$base_url) {
        return ['error' => 'missing_base_url'];
    }

    $headers = [
        'Content-Type' => 'application/json',
    ];
    if ($api_key) {
        $headers['Authorization'] = 'Bearer ' . $api_key;
    }

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_prompt],
        ],
    ];

    if (isset($options['temperature'])) {
        $payload['temperature'] = (float) $options['temperature'];
    }
    if (isset($options['top_p'])) {
        $payload['top_p'] = (float) $options['top_p'];
    }
    if (isset($options['num_predict'])) {
        $payload['max_tokens'] = (int) $options['num_predict'];
    }

    $response = wp_remote_post(rtrim($base_url, '/') . '/chat/completions', [
        'timeout' => 120,
        'headers' => $headers,
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        return ['error' => $response->get_error_message()];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($body)) {
        return ['error' => 'invalid_response'];
    }

    $content = $body['choices'][0]['message']['content'] ?? '';
    return ['text' => $content];
}

function ai_gateway_call_anthropic($api_key, $model, $system_prompt, $user_prompt, $options) {
    if (!$api_key) {
        return ['error' => 'missing_api_key'];
    }

    $payload = [
        'model' => $model,
        'max_tokens' => isset($options['num_predict']) ? (int) $options['num_predict'] : 1024,
        'system' => $system_prompt,
        'messages' => [
            ['role' => 'user', 'content' => $user_prompt],
        ],
    ];

    if (isset($options['temperature'])) {
        $payload['temperature'] = (float) $options['temperature'];
    }
    if (isset($options['top_p'])) {
        $payload['top_p'] = (float) $options['top_p'];
    }

    $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
        'timeout' => 120,
        'headers' => [
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        return ['error' => $response->get_error_message()];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($body)) {
        return ['error' => 'invalid_response'];
    }

    $content = $body['content'][0]['text'] ?? '';
    return ['text' => $content];
}

function ai_gateway_call_azure($model, $system_prompt, $user_prompt, $options) {
    $endpoint = defined('AI_GATEWAY_AZURE_ENDPOINT') ? AI_GATEWAY_AZURE_ENDPOINT : '';
    $api_key = defined('AI_GATEWAY_AZURE_KEY') ? AI_GATEWAY_AZURE_KEY : '';
    $deployment = defined('AI_GATEWAY_AZURE_DEPLOYMENT') ? AI_GATEWAY_AZURE_DEPLOYMENT : $model;

    if (!$endpoint || !$api_key || !$deployment) {
        return ['error' => 'missing_azure_config'];
    }

    $payload = [
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_prompt],
        ],
    ];

    if (isset($options['temperature'])) {
        $payload['temperature'] = (float) $options['temperature'];
    }
    if (isset($options['top_p'])) {
        $payload['top_p'] = (float) $options['top_p'];
    }
    if (isset($options['num_predict'])) {
        $payload['max_tokens'] = (int) $options['num_predict'];
    }

    $url = rtrim($endpoint, '/') . '/openai/deployments/' . rawurlencode($deployment) . '/chat/completions?api-version=2024-02-15-preview';

    $response = wp_remote_post($url, [
        'timeout' => 120,
        'headers' => [
            'Content-Type' => 'application/json',
            'api-key' => $api_key,
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        return ['error' => $response->get_error_message()];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($body)) {
        return ['error' => 'invalid_response'];
    }

    $content = $body['choices'][0]['message']['content'] ?? '';
    return ['text' => $content];
}

function ai_gateway_call_provider($agent, $instruction, $inputs) {
    $provider = ai_gateway_get_provider_for_agent($agent);
    $options = ai_gateway_get_ollama_options($agent);
    $system_prompt = $agent['system_prompt'] ?? '';
    $user_prompt = ai_gateway_build_user_prompt($instruction, $inputs);

    if ($provider === 'ollama') {
        return ['error' => 'use_ollama_handler'];
    }

    if ($provider === 'openai' || $provider === 'groq' || $provider === 'openrouter') {
        $base = 'https://api.openai.com/v1';
        if ($provider === 'groq') {
            $base = 'https://api.groq.com/openai/v1';
        } elseif ($provider === 'openrouter') {
            $base = 'https://openrouter.ai/api/v1';
        }
        $key = ai_gateway_get_provider_key($provider);
        return ai_gateway_call_openai_compatible($base, $key, $agent['model'], $system_prompt, $user_prompt, $options);
    }

    if ($provider === 'openai_compatible') {
        $source = ai_gateway_get_openai_compat_source_for_agent($agent);
        $base = ai_gateway_get_openai_compat_base($source);
        $key = ai_gateway_get_provider_key('openai_compatible', $source);
        return ai_gateway_call_openai_compatible($base, $key, $agent['model'], $system_prompt, $user_prompt, $options);
    }

    if ($provider === 'anthropic') {
        $key = ai_gateway_get_provider_key('anthropic');
        return ai_gateway_call_anthropic($key, $agent['model'], $system_prompt, $user_prompt, $options);
    }

    if ($provider === 'azure') {
        return ai_gateway_call_azure($agent['model'], $system_prompt, $user_prompt, $options);
    }

    return ['error' => 'unknown_provider'];
}
