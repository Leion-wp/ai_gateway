<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_register_execution_post_type() {
    register_post_type('ai_execution', [
        'labels' => [
            'name' => 'Executions',
            'singular_name' => 'Execution',
        ],
        'public' => false,
        'show_ui' => false,
        'show_in_menu' => false,
        'supports' => ['title'],
        'capability_type' => 'post',
    ]);
}

add_action('init', 'ai_gateway_register_execution_post_type');

function ai_gateway_get_logs_retention_days() {
    $days = (int) get_option('ai_gateway_logs_retention_days', 30);
    if ($days <= 0) {
        $days = 30;
    }
    return $days;
}

function ai_gateway_execution_preview($value, $limit = 280) {
    $value = wp_strip_all_tags((string) $value);
    $value = trim($value);
    if (strlen($value) <= $limit) {
        return $value;
    }
    return substr($value, 0, $limit) . '...';
}

function ai_gateway_redact_env_inputs($schema, $inputs) {
    if (!is_array($schema) || !is_array($inputs)) {
        return $inputs;
    }
    foreach ($schema as $field) {
        if (empty($field['env']) || empty($field['key'])) {
            continue;
        }
        $key = $field['key'];
        if (isset($inputs[$key])) {
            $inputs[$key] = '[env]';
        }
    }
    return $inputs;
}

function ai_gateway_log_execution($data) {
    $agent_id = isset($data['agent_id']) ? (int) $data['agent_id'] : 0;
    $agent_name = isset($data['agent_name']) ? sanitize_text_field($data['agent_name']) : '';
    $provider = isset($data['provider']) ? sanitize_text_field($data['provider']) : '';
    $model = isset($data['model']) ? sanitize_text_field($data['model']) : '';
    $status = isset($data['status']) ? sanitize_text_field($data['status']) : 'success';
    $duration_ms = isset($data['duration_ms']) ? (int) $data['duration_ms'] : 0;
    $error = isset($data['error']) ? sanitize_text_field($data['error']) : '';
    $input_full = isset($data['input_full']) ? $data['input_full'] : [];
    $output_full = isset($data['output_full']) ? (string) $data['output_full'] : '';
    $output_mode = isset($data['output_mode']) ? sanitize_text_field($data['output_mode']) : '';

    $title = $agent_name !== '' ? $agent_name : 'Execution';

    $post_id = wp_insert_post([
        'post_type' => 'ai_execution',
        'post_status' => 'publish',
        'post_title' => $title,
    ], true);

    if (is_wp_error($post_id)) {
        return 0;
    }

    $input_preview = '';
    if (is_array($input_full)) {
        $input_preview = ai_gateway_execution_preview(wp_json_encode($input_full, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    $output_preview = ai_gateway_execution_preview($output_full);

    update_post_meta($post_id, 'agent_id', $agent_id);
    update_post_meta($post_id, 'agent_name', $agent_name);
    update_post_meta($post_id, 'provider', $provider);
    update_post_meta($post_id, 'model', $model);
    update_post_meta($post_id, 'status', $status);
    update_post_meta($post_id, 'duration_ms', $duration_ms);
    update_post_meta($post_id, 'error', $error);
    update_post_meta($post_id, 'input_preview', $input_preview);
    update_post_meta($post_id, 'output_preview', $output_preview);
    update_post_meta($post_id, 'input_full', $input_full);
    update_post_meta($post_id, 'output_full', $output_full);
    update_post_meta($post_id, 'output_mode', $output_mode);

    return $post_id;
}

function ai_gateway_cleanup_executions() {
    $days = ai_gateway_get_logs_retention_days();
    $date = gmdate('Y-m-d', strtotime("-{$days} days"));
    $posts = get_posts([
        'post_type' => 'ai_execution',
        'post_status' => 'publish',
        'fields' => 'ids',
        'posts_per_page' => -1,
        'date_query' => [
            [
                'before' => $date,
                'inclusive' => true,
            ],
        ],
    ]);

    foreach ($posts as $post_id) {
        wp_delete_post($post_id, true);
    }
}

add_action('ai_gateway_cleanup_executions', 'ai_gateway_cleanup_executions');

function ai_gateway_schedule_execution_cleanup() {
    if (!wp_next_scheduled('ai_gateway_cleanup_executions')) {
        wp_schedule_event(time() + 3600, 'daily', 'ai_gateway_cleanup_executions');
    }
}

register_activation_hook(AI_GATEWAY_PLUGIN_FILE, 'ai_gateway_schedule_execution_cleanup');
