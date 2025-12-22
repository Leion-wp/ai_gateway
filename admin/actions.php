<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_ai_gateway_save_settings', 'ai_gateway_save_settings');
add_action('admin_post_ai_gateway_save_agent', 'ai_gateway_save_agent');
add_action('admin_post_ai_gateway_delete_agent', 'ai_gateway_delete_agent');
add_action('admin_post_ai_gateway_check_updates', 'ai_gateway_check_updates');

function ai_gateway_save_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ai_gateway_settings');

    $ollama_url = isset($_POST['ollama_url']) ? esc_url_raw(wp_unslash($_POST['ollama_url'])) : '';
    if ($ollama_url !== '') {
        update_option('ai_gateway_ollama_url', $ollama_url);
    }

    wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
    exit;
}

function ai_gateway_check_updates() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ai_gateway_check_updates');

    delete_transient('ai_gateway_update_info');
    wp_update_plugins();

    wp_redirect(admin_url('admin.php?page=ai-gateway-settings&updated=1'));
    exit;
}

function ai_gateway_save_agent() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ai_gateway_save_agent');

    $agent_id = isset($_POST['agent_id']) ? absint($_POST['agent_id']) : 0;
    $name = isset($_POST['agent_name']) ? sanitize_text_field(wp_unslash($_POST['agent_name'])) : '';
    $model = isset($_POST['agent_model']) ? sanitize_text_field(wp_unslash($_POST['agent_model'])) : '';
    $system_prompt = isset($_POST['agent_system_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['agent_system_prompt'])) : '';
    $input_schema_raw = isset($_POST['agent_input_schema']) ? wp_unslash($_POST['agent_input_schema']) : '';
    $mcp_endpoint = isset($_POST['agent_mcp_endpoint']) ? esc_url_raw(wp_unslash($_POST['agent_mcp_endpoint'])) : '';
    $output_mode = isset($_POST['agent_output_mode']) ? sanitize_text_field(wp_unslash($_POST['agent_output_mode'])) : 'text';
    $tools = isset($_POST['agent_tools']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['agent_tools'])) : [];
    $enabled = isset($_POST['agent_enabled']) ? '1' : '0';

    $input_schema = [];
    if ($input_schema_raw) {
        $decoded = json_decode($input_schema_raw, true);
        if (is_array($decoded)) {
            $input_schema = $decoded;
        }
    }

    $post_data = [
        'post_title' => $name,
        'post_type' => AI_GATEWAY_POST_TYPE,
        'post_status' => 'publish',
    ];

    if ($agent_id) {
        $post_data['ID'] = $agent_id;
        $agent_id = wp_update_post($post_data, true);
    } else {
        $agent_id = wp_insert_post($post_data, true);
    }

    if (is_wp_error($agent_id)) {
        wp_die($agent_id->get_error_message());
    }

    update_post_meta($agent_id, 'model', $model);
    update_post_meta($agent_id, 'system_prompt', $system_prompt);
    update_post_meta($agent_id, 'input_fields', wp_json_encode($input_schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    update_post_meta($agent_id, 'mcp_endpoint', $mcp_endpoint);
    update_post_meta($agent_id, 'output_mode', $output_mode);
    update_post_meta($agent_id, 'tools', wp_json_encode($tools, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    update_post_meta($agent_id, 'enabled', $enabled);

    wp_redirect(admin_url('admin.php?page=ai-gateway-agents&updated=1'));
    exit;
}

function ai_gateway_delete_agent() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ai_gateway_delete_agent');

    $agent_id = isset($_POST['agent_id']) ? absint($_POST['agent_id']) : 0;
    if ($agent_id) {
        wp_trash_post($agent_id);
    }

    wp_redirect(admin_url('admin.php?page=ai-gateway-agents&deleted=1'));
    exit;
}
