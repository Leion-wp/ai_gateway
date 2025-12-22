<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('_return_true')) {
    function _return_true() {
        return true;
    }
}

function ai_gateway_get_json_params($request) {
    $params = $request->get_json_params();
    return is_array($params) ? $params : [];
}

function ai_gateway_normalize_input_schema($schema) {
    if (!is_array($schema)) {
        return [];
    }

    $normalized = [];
    foreach ($schema as $item) {
        if (is_string($item)) {
            $key = sanitize_title($item);
            if ($key === '') {
                continue;
            }
            $normalized[] = [
                'label' => $item,
                'key' => $key,
                'type' => 'text',
                'required' => false,
                'placeholder' => '',
                'options' => [],
                'env' => '',
            ];
            continue;
        }

        if (!is_array($item)) {
            continue;
        }

        $label = isset($item['label']) ? sanitize_text_field($item['label']) : '';
        $key = isset($item['key']) ? sanitize_title($item['key']) : '';
        if ($label === '' && $key === '') {
            continue;
        }
        if ($key === '') {
            $key = sanitize_title($label);
        }

        $type = isset($item['type']) ? sanitize_text_field($item['type']) : 'text';
        $required = !empty($item['required']);
        $placeholder = isset($item['placeholder']) ? sanitize_text_field($item['placeholder']) : '';
        $options = [];
        if (!empty($item['options']) && is_array($item['options'])) {
            $options = array_values(array_filter(array_map('sanitize_text_field', $item['options'])));
        }
        $env = isset($item['env']) ? sanitize_text_field($item['env']) : '';

        $normalized[] = [
            'label' => $label !== '' ? $label : $key,
            'key' => $key,
            'type' => $type,
            'required' => $required,
            'placeholder' => $placeholder,
            'options' => $options,
            'env' => $env,
        ];
    }

    return $normalized;
}

function ai_gateway_apply_schema_env_values($schema, $inputs) {
    if (!is_array($schema)) {
        return $inputs;
    }
    foreach ($schema as $field) {
        if (empty($field['env']) || empty($field['key'])) {
            continue;
        }
        $key = $field['key'];
        if (!empty($inputs[$key])) {
            continue;
        }
        if (defined($field['env'])) {
            $inputs[$key] = constant($field['env']);
        }
    }
    return $inputs;
}
