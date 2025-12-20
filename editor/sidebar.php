<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_get_editor_settings() {
    return [
        'restUrl' => esc_url_raw(rest_url('ai/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'agents' => ai_gateway_get_agents(),
    ];
}