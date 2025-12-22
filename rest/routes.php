<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function() {
    register_rest_route('ai/v1', '/ping', [
        'methods' => 'GET',
        'callback' => function() {
            return ['status' => 'ok'];
        },
    ]);

    register_rest_route('ai/v1', '/run', [
        'methods' => 'POST',
        'callback' => 'ai_gateway_handle_run',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
    ]);

    register_rest_route('ai/v1', '/run/stream', [
        'methods' => 'POST',
        'callback' => 'ai_gateway_handle_run_stream',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
    ]);

    register_rest_route('ai/v1', '/publish', [
        'methods' => 'POST',
        'callback' => 'ai_gateway_handle_publish',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
    ]);

    register_rest_route('ai/v1', '/agents', [
        'methods' => 'GET',
        'callback' => 'ai_gateway_rest_list_agents',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    register_rest_route('ai/v1', '/agents', [
        'methods' => 'POST',
        'callback' => 'ai_gateway_rest_create_agent',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    register_rest_route('ai/v1', '/agents/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'ai_gateway_rest_get_agent',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    register_rest_route('ai/v1', '/agents/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'ai_gateway_rest_update_agent',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    register_rest_route('ai/v1', '/agents/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'ai_gateway_rest_delete_agent',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    register_rest_route('ai/v1', '/media/import', [
        'methods' => 'POST',
        'callback' => 'ai_gateway_rest_media_import',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
    ]);

    register_rest_route('ai/v1', '/plugins', [
        'methods' => 'GET',
        'callback' => 'ai_gateway_rest_list_plugins',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    register_rest_route('ai/v1', '/plugins/activate', [
        'methods' => 'POST',
        'callback' => 'ai_gateway_rest_activate_plugin',
        'permission_callback' => function() {
            return current_user_can('activate_plugins');
        },
    ]);

    register_rest_route('ai/v1', '/plugins/deactivate', [
        'methods' => 'POST',
        'callback' => 'ai_gateway_rest_deactivate_plugin',
        'permission_callback' => function() {
            return current_user_can('activate_plugins');
        },
    ]);
});
