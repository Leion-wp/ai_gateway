<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_rest_media_import($request) {
    $params = ai_gateway_get_json_params($request);
    $url = isset($params['url']) ? esc_url_raw($params['url']) : '';

    if (!$url) {
        return new WP_Error('invalid', 'URL manquante', ['status' => 400]);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url($url);
    if (is_wp_error($tmp)) {
        return $tmp;
    }

    $file = [
        'name' => basename($url),
        'tmp_name' => $tmp,
    ];

    $attachment_id = media_handle_sideload($file, 0);
    if (is_wp_error($attachment_id)) {
        @unlink($tmp);
        return $attachment_id;
    }

    return [
        'attachment_id' => $attachment_id,
        'url' => wp_get_attachment_url($attachment_id),
    ];
}