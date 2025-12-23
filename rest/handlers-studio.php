<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_format_project($post) {
    return [
        'id' => $post->ID,
        'name' => $post->post_title,
    ];
}

function ai_gateway_format_conversation($post) {
    $messages = get_post_meta($post->ID, 'messages', true);
    $last = '';
    if (is_array($messages) && !empty($messages)) {
        $last_message = end($messages);
        if (is_array($last_message) && isset($last_message['content'])) {
            $last = ai_gateway_execution_preview($last_message['content'], 160);
        }
    }

    return [
        'id' => $post->ID,
        'name' => $post->post_title,
        'project_id' => (int) get_post_meta($post->ID, 'project_id', true),
        'last' => $last,
    ];
}

function ai_gateway_rest_list_projects($request) {
    $posts = get_posts([
        'post_type' => AI_GATEWAY_PROJECT_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    return array_map('ai_gateway_format_project', $posts);
}

function ai_gateway_rest_create_project($request) {
    $params = ai_gateway_get_json_params($request);
    $name = isset($params['name']) ? sanitize_text_field($params['name']) : '';
    if ($name === '') {
        return new WP_Error('invalid', 'Name required', ['status' => 400]);
    }

    $post_id = wp_insert_post([
        'post_title' => $name,
        'post_type' => AI_GATEWAY_PROJECT_POST_TYPE,
        'post_status' => 'publish',
    ], true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    $post = get_post($post_id);
    return ai_gateway_format_project($post);
}

function ai_gateway_rest_list_conversations($request) {
    $project_id = absint($request->get_param('project_id'));
    $args = [
        'post_type' => AI_GATEWAY_CONVERSATION_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    if ($project_id) {
        $args['meta_query'] = [
            [
                'key' => 'project_id',
                'value' => (string) $project_id,
                'compare' => '=',
            ],
        ];
    }

    $posts = get_posts($args);
    return array_map('ai_gateway_format_conversation', $posts);
}

function ai_gateway_rest_create_conversation($request) {
    $params = ai_gateway_get_json_params($request);
    $name = isset($params['name']) ? sanitize_text_field($params['name']) : 'Conversation';
    $project_id = isset($params['project_id']) ? absint($params['project_id']) : 0;

    $post_id = wp_insert_post([
        'post_title' => $name,
        'post_type' => AI_GATEWAY_CONVERSATION_POST_TYPE,
        'post_status' => 'publish',
    ], true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    if ($project_id) {
        update_post_meta($post_id, 'project_id', $project_id);
    }
    update_post_meta($post_id, 'messages', []);

    $post = get_post($post_id);
    return ai_gateway_format_conversation($post);
}

function ai_gateway_rest_get_conversation($request) {
    $conversation_id = absint($request['id']);
    $post = get_post($conversation_id);
    if (!$post || $post->post_type !== AI_GATEWAY_CONVERSATION_POST_TYPE) {
        return new WP_Error('not_found', 'Conversation not found', ['status' => 404]);
    }

    $messages = get_post_meta($conversation_id, 'messages', true);
    if (!is_array($messages)) {
        $messages = [];
    }

    $data = ai_gateway_format_conversation($post);
    $data['messages'] = $messages;
    return $data;
}

function ai_gateway_rest_append_message($request) {
    $conversation_id = absint($request['id']);
    $post = get_post($conversation_id);
    if (!$post || $post->post_type !== AI_GATEWAY_CONVERSATION_POST_TYPE) {
        return new WP_Error('not_found', 'Conversation not found', ['status' => 404]);
    }

    $params = ai_gateway_get_json_params($request);
    $role = isset($params['role']) ? sanitize_text_field($params['role']) : '';
    $content = isset($params['content']) ? sanitize_textarea_field($params['content']) : '';
    if ($role === '' || $content === '') {
        return new WP_Error('invalid', 'Role and content required', ['status' => 400]);
    }

    $messages = get_post_meta($conversation_id, 'messages', true);
    if (!is_array($messages)) {
        $messages = [];
    }
    $messages[] = [
        'role' => $role,
        'content' => $content,
        'created_at' => gmdate('c'),
    ];

    update_post_meta($conversation_id, 'messages', $messages);

    $data = ai_gateway_format_conversation($post);
    $data['messages'] = $messages;
    return $data;
}
