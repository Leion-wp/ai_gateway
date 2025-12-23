<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function() {
    add_menu_page('Agents IA', 'Agents IA', 'manage_options', 'ai-gateway-agents', 'ai_gateway_render_agents_page');
    add_submenu_page('ai-gateway-agents', 'Reglages', 'Reglages', 'manage_options', 'ai-gateway-settings', 'ai_gateway_render_settings_page');
    add_submenu_page('ai-gateway-agents', 'Executions', 'Executions', 'manage_options', 'ai-gateway-executions', 'ai_gateway_render_executions_page');
    add_submenu_page('ai-gateway-agents', 'Plugins IA', 'Plugins IA', 'manage_options', 'ai-gateway-plugins', 'ai_gateway_render_plugins_page');
});
