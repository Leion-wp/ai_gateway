<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_get_ollama_url() {
    return get_option('ai_gateway_ollama_url', 'http://localhost:11434/api/generate');
}

function ai_gateway_get_ollama_presets() {
    $defaults = [
        'speed' => [
            'label' => 'Rapidite',
            'options' => [
                'temperature' => 0.2,
                'top_p' => 0.8,
                'top_k' => 20,
                'num_predict' => 512,
            ],
        ],
        'balanced' => [
            'label' => 'Equilibre',
            'options' => [
                'temperature' => 0.4,
                'top_p' => 0.9,
                'top_k' => 40,
                'num_predict' => 1024,
            ],
        ],
        'quality' => [
            'label' => 'Qualite',
            'options' => [
                'temperature' => 0.7,
                'top_p' => 0.95,
                'top_k' => 50,
                'num_predict' => 2048,
            ],
        ],
    ];

    $stored = get_option('ai_gateway_ollama_presets', []);
    if (!is_array($stored) || empty($stored)) {
        return $defaults;
    }

    return $stored + $defaults;
}

function ai_gateway_get_ollama_preset() {
    $preset = get_option('ai_gateway_ollama_preset', 'balanced');
    $presets = ai_gateway_get_ollama_presets();
    if (!isset($presets[$preset])) {
        $preset = 'balanced';
    }
    return $preset;
}

function ai_gateway_get_ollama_options($agent = null) {
    $presets = ai_gateway_get_ollama_presets();
    $preset = ai_gateway_get_ollama_preset();
    $agent_preset = '';
    if (is_array($agent) && !empty($agent['ollama_preset'])) {
        $agent_preset = $agent['ollama_preset'];
    }
    if ($agent_preset && isset($presets[$agent_preset])) {
        $preset = $agent_preset;
    }

    $options = $presets[$preset]['options'] ?? [];

    $advanced = [
        'num_predict' => 'ai_gateway_ollama_num_predict',
        'num_ctx' => 'ai_gateway_ollama_num_ctx',
        'num_gpu' => 'ai_gateway_ollama_num_gpu',
        'num_thread' => 'ai_gateway_ollama_num_thread',
        'temperature' => 'ai_gateway_ollama_temperature',
        'top_p' => 'ai_gateway_ollama_top_p',
        'top_k' => 'ai_gateway_ollama_top_k',
        'repeat_penalty' => 'ai_gateway_ollama_repeat_penalty',
        'seed' => 'ai_gateway_ollama_seed',
    ];

    foreach ($advanced as $key => $option_name) {
        $value = get_option($option_name, '');
        if ($value === '' || $value === null) {
            continue;
        }
        if (in_array($key, ['temperature', 'top_p', 'repeat_penalty'], true)) {
            $options[$key] = (float) $value;
        } else {
            $options[$key] = (int) $value;
        }
    }

    return $options;
}
