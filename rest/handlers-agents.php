<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_rest_list_agents($request) {
    $only_enabled = $request->get_param('enabled') === '1';
    return ai_gateway_get_agents($only_enabled);
}

function ai_gateway_rest_get_agent($request) {
    $agent_id = absint($request['id']);
    $agent = ai_gateway_get_agent($agent_id);
    if (!$agent) {
        return new WP_Error('not_found', 'Agent introuvable', ['status' => 404]);
    }
    return $agent;
}

function ai_gateway_rest_create_agent($request) {
    $params = ai_gateway_get_json_params($request);
    $name = isset($params['name']) ? sanitize_text_field($params['name']) : '';
    if (!$name) {
        return new WP_Error('invalid', 'Nom requis', ['status' => 400]);
    }

    $post_id = wp_insert_post([
        'post_title' => $name,
        'post_type' => AI_GATEWAY_POST_TYPE,
        'post_status' => 'publish',
    ], true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    ai_gateway_update_agent_meta($post_id, $params);
    return ai_gateway_get_agent($post_id);
}

function ai_gateway_rest_update_agent($request) {
    $agent_id = absint($request['id']);
    $agent = ai_gateway_get_agent($agent_id);
    if (!$agent) {
        return new WP_Error('not_found', 'Agent introuvable', ['status' => 404]);
    }

    $params = ai_gateway_get_json_params($request);
    if (isset($params['name'])) {
        wp_update_post([
            'ID' => $agent_id,
            'post_title' => sanitize_text_field($params['name']),
        ]);
    }

    ai_gateway_update_agent_meta($agent_id, $params);
    return ai_gateway_get_agent($agent_id);
}

function ai_gateway_rest_delete_agent($request) {
    $agent_id = absint($request['id']);
    $agent = ai_gateway_get_agent($agent_id);
    if (!$agent) {
        return new WP_Error('not_found', 'Agent introuvable', ['status' => 404]);
    }

    wp_trash_post($agent_id);
    return ['status' => 'archived', 'id' => $agent_id];
}
