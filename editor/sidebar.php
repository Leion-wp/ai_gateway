<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_get_editor_settings() {
    return [
        'restUrl' => esc_url_raw(rest_url('ai/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'agents' => ai_gateway_get_agents(),
        'studioDefaultAgent' => (int) get_option('ai_gateway_studio_default_agent', 0),
        'adminUrl' => esc_url_raw(admin_url()),
    ];
}
