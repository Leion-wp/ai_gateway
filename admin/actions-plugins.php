<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_ai_gateway_activate_plugin', 'ai_gateway_admin_activate_plugin');
add_action('admin_post_ai_gateway_deactivate_plugin', 'ai_gateway_admin_deactivate_plugin');

function ai_gateway_admin_activate_plugin() {
    if (!current_user_can('activate_plugins')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ai_gateway_plugin_action');

    $plugin_file = isset($_POST['plugin_file']) ? sanitize_text_field(wp_unslash($_POST['plugin_file'])) : '';
    if ($plugin_file) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $result = activate_plugin($plugin_file);
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
    }

    wp_redirect(admin_url('admin.php?page=ai-gateway-plugins&updated=1'));
    exit;
}

function ai_gateway_admin_deactivate_plugin() {
    if (!current_user_can('activate_plugins')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ai_gateway_plugin_action');

    $plugin_file = isset($_POST['plugin_file']) ? sanitize_text_field(wp_unslash($_POST['plugin_file'])) : '';
    if ($plugin_file) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        deactivate_plugins($plugin_file);
    }

    wp_redirect(admin_url('admin.php?page=ai-gateway-plugins&updated=1'));
    exit;
}