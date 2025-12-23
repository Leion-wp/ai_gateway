<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function() {
    add_menu_page('Agents IA', 'Agents IA', 'manage_options', 'ai-gateway-agents', 'ai_gateway_render_agents_page');
    add_submenu_page('ai-gateway-agents', 'AI Studio', 'AI Studio', ai_gateway_get_studio_capability(), 'ai-gateway-studio', '__return_null');
    add_submenu_page('ai-gateway-agents', 'Reglages', 'Reglages', 'manage_options', 'ai-gateway-settings', 'ai_gateway_render_settings_page');
    add_submenu_page('ai-gateway-agents', 'Executions', 'Executions', 'manage_options', 'ai-gateway-executions', 'ai_gateway_render_executions_page');
    add_submenu_page('ai-gateway-agents', 'Plugins IA', 'Plugins IA', 'manage_options', 'ai-gateway-plugins', 'ai_gateway_render_plugins_page');
});

function ai_gateway_redirect_studio_editor() {
    if (!is_admin() || empty($_GET['page']) || $_GET['page'] !== 'ai-gateway-studio') {
        return;
    }
    if (!current_user_can(ai_gateway_get_studio_capability())) {
        wp_die('Unauthorized');
    }

    $post_id = ai_gateway_get_studio_post_id();
    if (!$post_id) {
        ai_gateway_seed_studio_post();
        $post_id = ai_gateway_get_studio_post_id();
    }

    if ($post_id) {
        wp_safe_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
        exit;
    }

    wp_die('AI Studio page not found.');
}

add_action('admin_init', 'ai_gateway_redirect_studio_editor');
