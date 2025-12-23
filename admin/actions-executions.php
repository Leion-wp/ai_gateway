<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_ai_gateway_download_execution', 'ai_gateway_download_execution');

function ai_gateway_download_execution() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ai_gateway_download_execution');

    $execution_id = isset($_POST['execution_id']) ? absint($_POST['execution_id']) : 0;
    if (!$execution_id) {
        wp_die('Invalid execution');
    }

    $post = get_post($execution_id);
    if (!$post || $post->post_type !== 'ai_execution') {
        wp_die('Execution not found');
    }

    $report = [
        'id' => $execution_id,
        'date' => get_the_date('c', $execution_id),
        'agent_id' => (int) get_post_meta($execution_id, 'agent_id', true),
        'agent_name' => get_post_meta($execution_id, 'agent_name', true),
        'provider' => get_post_meta($execution_id, 'provider', true),
        'model' => get_post_meta($execution_id, 'model', true),
        'status' => get_post_meta($execution_id, 'status', true),
        'duration_ms' => (int) get_post_meta($execution_id, 'duration_ms', true),
        'error' => get_post_meta($execution_id, 'error', true),
        'input_full' => get_post_meta($execution_id, 'input_full', true),
        'output_full' => get_post_meta($execution_id, 'output_full', true),
        'output_mode' => get_post_meta($execution_id, 'output_mode', true),
    ];

    $filename = 'ai-execution-' . $execution_id . '.json';

    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    echo wp_json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
