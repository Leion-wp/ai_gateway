<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_get_studio_asset() {
    $asset_path = AI_GATEWAY_PLUGIN_DIR . 'build/studio.asset.php';
    if (file_exists($asset_path)) {
        return include $asset_path;
    }

    return [
        'dependencies' => [
            'wp-element',
            'wp-components',
            'wp-data',
            'wp-block-editor',
            'wp-blocks',
            'wp-i18n',
            'wp-api-fetch',
        ],
        'version' => AI_GATEWAY_VERSION,
    ];
}

function ai_gateway_enqueue_studio_editor_assets() {
    if (!function_exists('get_current_screen')) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== AI_GATEWAY_STUDIO_POST_TYPE) {
        return;
    }

    $script_path = AI_GATEWAY_PLUGIN_DIR . 'build/studio.js';
    if (!file_exists($script_path)) {
        return;
    }

    $asset = ai_gateway_get_studio_asset();

    wp_enqueue_script(
        'ai-gateway-studio',
        AI_GATEWAY_PLUGIN_URL . 'build/studio.js',
        $asset['dependencies'],
        $asset['version'],
        true
    );

    wp_localize_script('ai-gateway-studio', 'AIGatewaySettings', ai_gateway_get_editor_settings());
}

function ai_gateway_enqueue_studio_assets() {
    if (!function_exists('ai_gateway_is_studio_request') || !ai_gateway_is_studio_request()) {
        return;
    }

    $script_path = AI_GATEWAY_PLUGIN_DIR . 'build/studio.js';
    if (!file_exists($script_path)) {
        return;
    }

    $asset = ai_gateway_get_studio_asset();

    wp_enqueue_script(
        'ai-gateway-studio',
        AI_GATEWAY_PLUGIN_URL . 'build/studio.js',
        $asset['dependencies'],
        $asset['version'],
        true
    );

    wp_localize_script('ai-gateway-studio', 'AIGatewaySettings', ai_gateway_get_editor_settings());
}

add_action('enqueue_block_editor_assets', 'ai_gateway_enqueue_studio_editor_assets');
add_action('wp_enqueue_scripts', 'ai_gateway_enqueue_studio_assets');
