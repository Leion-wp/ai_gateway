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
        'archived' => get_post_meta($post->ID, 'archived', true) === '1',
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
    $include_archived = $request->get_param('include_archived') === '1';
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
    if (!$include_archived) {
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = [];
        }
        $args['meta_query'][] = [
            'key' => 'archived',
            'compare' => 'NOT EXISTS',
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

function ai_gateway_rest_update_conversation_draft($request) {
    $conversation_id = absint($request['id']);
    $post = get_post($conversation_id);
    if (!$post || $post->post_type !== AI_GATEWAY_CONVERSATION_POST_TYPE) {
        return new WP_Error('not_found', 'Conversation not found', ['status' => 404]);
    }

    $params = ai_gateway_get_json_params($request);
    $messages = isset($params['messages']) && is_array($params['messages']) ? $params['messages'] : [];
    if (empty($messages)) {
        return ['status' => 'skipped'];
    }

    $lines = [];
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }
        $role = isset($message['role']) ? strtoupper(sanitize_text_field($message['role'])) : 'USER';
        $content = isset($message['content']) ? sanitize_textarea_field($message['content']) : '';
        if ($content === '') {
            continue;
        }
        $lines[] = $role . ': ' . $content;
    }

    $content = implode("\n\n", $lines);
    if ($content === '') {
        return ['status' => 'skipped'];
    }

    $draft_id = (int) get_post_meta($conversation_id, 'draft_post_id', true);
    $title = 'Chat: ' . $post->post_title;

    if ($draft_id) {
        wp_update_post([
            'ID' => $draft_id,
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'draft',
        ]);
    } else {
        $draft_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => 'post',
        ], true);
        if (!is_wp_error($draft_id)) {
            update_post_meta($conversation_id, 'draft_post_id', (int) $draft_id);
        }
    }

    return ['status' => 'ok', 'draft_id' => (int) $draft_id];
}

function ai_gateway_rest_update_conversation($request) {
    $conversation_id = absint($request['id']);
    $post = get_post($conversation_id);
    if (!$post || $post->post_type !== AI_GATEWAY_CONVERSATION_POST_TYPE) {
        return new WP_Error('not_found', 'Conversation not found', ['status' => 404]);
    }

    $params = ai_gateway_get_json_params($request);
    if (isset($params['name'])) {
        wp_update_post([
            'ID' => $conversation_id,
            'post_title' => sanitize_text_field($params['name']),
        ]);
    }
    if (isset($params['project_id'])) {
        update_post_meta($conversation_id, 'project_id', absint($params['project_id']));
    }

    $post = get_post($conversation_id);
    return ai_gateway_format_conversation($post);
}

function ai_gateway_rest_delete_conversation($request) {
    $conversation_id = absint($request['id']);
    $post = get_post($conversation_id);
    if (!$post || $post->post_type !== AI_GATEWAY_CONVERSATION_POST_TYPE) {
        return new WP_Error('not_found', 'Conversation not found', ['status' => 404]);
    }
    wp_delete_post($conversation_id, true);
    return ['status' => 'deleted', 'id' => $conversation_id];
}

function ai_gateway_rest_archive_conversation($request) {
    $conversation_id = absint($request['id']);
    $post = get_post($conversation_id);
    if (!$post || $post->post_type !== AI_GATEWAY_CONVERSATION_POST_TYPE) {
        return new WP_Error('not_found', 'Conversation not found', ['status' => 404]);
    }
    update_post_meta($conversation_id, 'archived', '1');
    return ['status' => 'archived', 'id' => $conversation_id];
}

function ai_gateway_rest_update_project($request) {
    $project_id = absint($request['id']);
    $post = get_post($project_id);
    if (!$post || $post->post_type !== AI_GATEWAY_PROJECT_POST_TYPE) {
        return new WP_Error('not_found', 'Project not found', ['status' => 404]);
    }

    $params = ai_gateway_get_json_params($request);
    if (isset($params['name'])) {
        wp_update_post([
            'ID' => $project_id,
            'post_title' => sanitize_text_field($params['name']),
        ]);
    }

    $post = get_post($project_id);
    return ai_gateway_format_project($post);
}

function ai_gateway_rest_delete_project($request) {
    $project_id = absint($request['id']);
    $post = get_post($project_id);
    if (!$post || $post->post_type !== AI_GATEWAY_PROJECT_POST_TYPE) {
        return new WP_Error('not_found', 'Project not found', ['status' => 404]);
    }
    wp_delete_post($project_id, true);
    return ['status' => 'deleted', 'id' => $project_id];
}

function ai_gateway_rest_get_workflow($request) {
    $conversation_id = absint($request['id']);
    $post = get_post($conversation_id);
    if (!$post || $post->post_type !== AI_GATEWAY_CONVERSATION_POST_TYPE) {
        return new WP_Error('not_found', 'Conversation not found', ['status' => 404]);
    }

    $workflow = get_post_meta($conversation_id, 'workflow', true);
    if (!is_array($workflow)) {
        $workflow = [];
    }
    return ['workflow' => $workflow];
}

function ai_gateway_rest_update_workflow($request) {
    $conversation_id = absint($request['id']);
    $post = get_post($conversation_id);
    if (!$post || $post->post_type !== AI_GATEWAY_CONVERSATION_POST_TYPE) {
        return new WP_Error('not_found', 'Conversation not found', ['status' => 404]);
    }

    $params = ai_gateway_get_json_params($request);
    $workflow = isset($params['workflow']) && is_array($params['workflow']) ? $params['workflow'] : [];
    update_post_meta($conversation_id, 'workflow', $workflow);
    return ['workflow' => $workflow];
}
