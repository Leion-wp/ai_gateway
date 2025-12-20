<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_rest_list_plugins($request) {
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Unauthorized', ['status' => 403]);
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $plugins = get_plugins();
    $items = [];

    foreach ($plugins as $file => $data) {
        $items[] = [
            'file' => $file,
            'name' => $data['Name'],
            'version' => $data['Version'],
            'active' => is_plugin_active($file),
        ];
    }

    return $items;
}

function ai_gateway_rest_activate_plugin($request) {
    if (!current_user_can('activate_plugins')) {
        return new WP_Error('forbidden', 'Unauthorized', ['status' => 403]);
    }

    $params = ai_gateway_get_json_params($request);
    $plugin_file = isset($params['plugin_file']) ? sanitize_text_field($params['plugin_file']) : '';
    if (!$plugin_file) {
        return new WP_Error('invalid', 'Plugin file required', ['status' => 400]);
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $result = activate_plugin($plugin_file);
    if (is_wp_error($result)) {
        return $result;
    }

    return ['status' => 'activated', 'plugin_file' => $plugin_file];
}

function ai_gateway_rest_deactivate_plugin($request) {
    if (!current_user_can('activate_plugins')) {
        return new WP_Error('forbidden', 'Unauthorized', ['status' => 403]);
    }

    $params = ai_gateway_get_json_params($request);
    $plugin_file = isset($params['plugin_file']) ? sanitize_text_field($params['plugin_file']) : '';
    if (!$plugin_file) {
        return new WP_Error('invalid', 'Plugin file required', ['status' => 400]);
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    deactivate_plugins($plugin_file);

    return ['status' => 'deactivated', 'plugin_file' => $plugin_file];
}