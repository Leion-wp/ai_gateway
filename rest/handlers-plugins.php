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

function ai_gateway_rest_search_plugins($request) {
    if (!current_user_can('install_plugins')) {
        return new WP_Error('forbidden', 'Unauthorized', ['status' => 403]);
    }

    $query = sanitize_text_field($request->get_param('query'));
    if ($query === '') {
        return new WP_Error('invalid', 'Query required', ['status' => 400]);
    }

    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

    $api = plugins_api('query_plugins', [
        'search' => $query,
        'page' => 1,
        'per_page' => 10,
        'fields' => [
            'short_description' => true,
            'icons' => true,
            'downloads' => true,
            'active_installs' => true,
            'rating' => true,
        ],
    ]);

    if (is_wp_error($api)) {
        return $api;
    }

    $results = [];
    foreach ($api->plugins as $plugin) {
        $results[] = [
            'slug' => $plugin->slug,
            'name' => $plugin->name,
            'version' => $plugin->version,
            'description' => wp_strip_all_tags($plugin->short_description),
            'active_installs' => $plugin->active_installs,
            'rating' => $plugin->rating,
            'icon' => !empty($plugin->icons['default']) ? $plugin->icons['default'] : '',
        ];
    }

    return $results;
}

function ai_gateway_rest_install_plugin($request) {
    if (!current_user_can('install_plugins')) {
        return new WP_Error('forbidden', 'Unauthorized', ['status' => 403]);
    }

    $params = ai_gateway_get_json_params($request);
    $slug = isset($params['slug']) ? sanitize_text_field($params['slug']) : '';
    if ($slug === '') {
        return new WP_Error('invalid', 'Slug required', ['status' => 400]);
    }

    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';

    $api = plugins_api('plugin_information', [
        'slug' => $slug,
        'fields' => [
            'sections' => false,
            'short_description' => true,
        ],
    ]);

    if (is_wp_error($api)) {
        return $api;
    }

    $skin = new Automatic_Upgrader_Skin();
    $upgrader = new Plugin_Upgrader($skin);
    $result = $upgrader->install($api->download_link);

    if (is_wp_error($result) || !$result) {
        $error = is_wp_error($result) ? $result : new WP_Error('install_failed', 'Install failed');
        return $error;
    }

    return [
        'status' => 'installed',
        'slug' => $slug,
    ];
}

function ai_gateway_rest_delete_plugin($request) {
    if (!current_user_can('delete_plugins')) {
        return new WP_Error('forbidden', 'Unauthorized', ['status' => 403]);
    }

    $params = ai_gateway_get_json_params($request);
    $plugin_file = isset($params['plugin_file']) ? sanitize_text_field($params['plugin_file']) : '';
    if (!$plugin_file) {
        return new WP_Error('invalid', 'Plugin file required', ['status' => 400]);
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    $result = delete_plugins([$plugin_file]);
    if (is_wp_error($result)) {
        return $result;
    }

    return ['status' => 'deleted', 'plugin_file' => $plugin_file];
}
