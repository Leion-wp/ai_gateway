<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_get_editor_asset() {
    $asset_path = AI_GATEWAY_PLUGIN_DIR . 'build/index.asset.php';
    if (file_exists($asset_path)) {
        return include $asset_path;
    }

    return [
        'dependencies' => [
            'wp-plugins',
            'wp-edit-post',
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

function ai_gateway_enqueue_editor_assets() {
    $asset = ai_gateway_get_editor_asset();

    wp_enqueue_script(
        'ai-gateway-editor',
        AI_GATEWAY_PLUGIN_URL . 'build/index.js',
        $asset['dependencies'],
        $asset['version'],
        true
    );

    wp_localize_script('ai-gateway-editor', 'AIGatewaySettings', ai_gateway_get_editor_settings());
}

add_action('enqueue_block_editor_assets', 'ai_gateway_enqueue_editor_assets');