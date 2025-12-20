<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_handle_publish($request) {
    $params = ai_gateway_get_json_params($request);
    $title = isset($params['title']) ? sanitize_text_field($params['title']) : 'Agent IA';
    $content = isset($params['content']) ? wp_kses_post($params['content']) : '';

    if (!$content) {
        return ['error' => 'Aucun contenu a publier.'];
    }

    $post_id = wp_insert_post([
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => 'draft',
    ], true);

    if (is_wp_error($post_id)) {
        return ['error' => $post_id->get_error_message()];
    }

    return [
        'status' => 'draft',
        'edit_link' => get_edit_post_link($post_id, 'raw'),
    ];
}