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
        'permission_callback' => '_return_true',
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

    register_rest_route('ai/v1', '/ollama/pull/stream', [
        'methods' => 'POST',
        'callback' => 'ai_gateway_rest_pull_model_stream',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    register_rest_route('ai/v1', '/projects', [
        'methods' => 'GET',
        'callback' => 'ai_gateway_rest_list_projects',
        'permission_callback' => function() {
            return current_user_can('edit_pages');
        },
    ]);

    register_rest_route('ai/v1', '/projects', [
        'methods' => 'POST',
        'callback' => 'ai_gateway_rest_create_project',
        'permission_callback' => function() {
            return current_user_can('edit_pages');
        },
    ]);

    register_rest_route('ai/v1', '/conversations', [
        'methods' => 'GET',
        'callback' => 'ai_gateway_rest_list_conversations',
        'permission_callback' => function() {
            return current_user_can('edit_pages');
        },
    ]);

    register_rest_route('ai/v1', '/conversations', [
        'methods' => 'POST',
        'callback' => 'ai_gateway_rest_create_conversation',
        'permission_callback' => function() {
            return current_user_can('edit_pages');
        },
    ]);

    register_rest_route('ai/v1', '/conversations/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'ai_gateway_rest_get_conversation',
        'permission_callback' => function() {
            return current_user_can('edit_pages');
        },
    ]);

    register_rest_route('ai/v1', '/conversations/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'ai_gateway_rest_update_conversation',
        'permission_callback' => function() {
            return current_user_can('edit_pages');
        },
    ]);

    register_rest_route('ai/v1', '/conversations/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'ai_gateway_rest_delete_conversation',
        'permission_callback' => function() {
            return current_user_can('edit_pages');
        },
    ]);

    register_rest_route('ai/v1', '/conversations/(?P<id>\d+)/archive', [
        'methods' => 'POST',
        'callback' => 'ai_gateway_rest_archive_conversation',
        'permission_callback' => function() {
            return current_user_can('edit_pages');
        },
    ]);

    register_rest_route('ai/v1', '/conversations/(?P<id>\d+)/messages', [
        'methods' => 'POST',
        'callback' => 'ai_gateway_rest_append_message',
        'permission_callback' => function() {
            return current_user_can('edit_pages');
        },
    ]);

    register_rest_route('ai/v1', '/conversations/(?P<id>\d+)/draft', [
        'methods' => 'POST',
        'callback' => 'ai_gateway_rest_update_conversation_draft',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
    ]);

    register_rest_route('ai/v1', '/projects/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'ai_gateway_rest_update_project',
        'permission_callback' => function() {
            return current_user_can('edit_pages');
        },
    ]);

    register_rest_route('ai/v1', '/projects/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'ai_gateway_rest_delete_project',
        'permission_callback' => function() {
            return current_user_can('edit_pages');
        },
    ]);
});
