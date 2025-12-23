<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_register_studio_post_types() {
    $args = [
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'supports' => ['title', 'editor'],
        'show_in_rest' => true,
        'capability_type' => 'page',
        'map_meta_cap' => true,
    ];

    register_post_type(AI_GATEWAY_STUDIO_POST_TYPE, [
        'labels' => [
            'name' => 'AI Studio',
            'singular_name' => 'AI Studio',
        ],
        'rewrite' => ['slug' => 'ai-studio'],
    ] + $args);

    register_post_type(AI_GATEWAY_PROJECT_POST_TYPE, [
        'labels' => [
            'name' => 'Projects',
            'singular_name' => 'Project',
        ],
        'supports' => ['title'],
    ] + $args);

    register_post_type(AI_GATEWAY_CONVERSATION_POST_TYPE, [
        'labels' => [
            'name' => 'Conversations',
            'singular_name' => 'Conversation',
        ],
        'supports' => ['title'],
    ] + $args);
}

add_action('init', 'ai_gateway_register_studio_post_types');

function ai_gateway_is_studio_request() {
    return is_singular(AI_GATEWAY_STUDIO_POST_TYPE);
}

function ai_gateway_get_studio_capability() {
    $cap = get_option('ai_gateway_studio_capability', 'edit_pages');
    if (!in_array($cap, ['edit_pages', 'manage_options'], true)) {
        $cap = 'edit_pages';
    }
    return $cap;
}

function ai_gateway_is_studio_fullscreen() {
    return get_option('ai_gateway_studio_fullscreen', '1') === '1';
}

function ai_gateway_require_studio_access() {
    if (!ai_gateway_is_studio_request()) {
        return;
    }

    if (!is_user_logged_in()) {
        auth_redirect();
    }

    if (!current_user_can(ai_gateway_get_studio_capability())) {
        wp_die('Unauthorized');
    }
}

add_action('template_redirect', 'ai_gateway_require_studio_access');

function ai_gateway_studio_template($template) {
    if (!ai_gateway_is_studio_request()) {
        return $template;
    }

    if (!ai_gateway_is_studio_fullscreen()) {
        return $template;
    }

    $custom = AI_GATEWAY_PLUGIN_DIR . 'templates/ai-studio.php';
    if (file_exists($custom)) {
        return $custom;
    }

    return $template;
}

add_filter('template_include', 'ai_gateway_studio_template');

function ai_gateway_get_studio_post_id() {
    $stored = (int) get_option('ai_gateway_studio_post_id', 0);
    if ($stored) {
        $post = get_post($stored);
        if ($post && $post->post_type === AI_GATEWAY_STUDIO_POST_TYPE) {
            return $stored;
        }
    }

    $existing = get_posts([
        'post_type' => AI_GATEWAY_STUDIO_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);

    if (!empty($existing)) {
        update_option('ai_gateway_studio_post_id', (int) $existing[0]);
        return (int) $existing[0];
    }

    return 0;
}

function ai_gateway_seed_studio_post() {
    $post_id = ai_gateway_get_studio_post_id();
    if ($post_id) {
        return;
    }

    $content = "<!-- wp:paragraph -->\n<p>AI Studio</p>\n<!-- /wp:paragraph -->";

    $post_id = wp_insert_post([
        'post_title' => 'AI Studio',
        'post_type' => AI_GATEWAY_STUDIO_POST_TYPE,
        'post_status' => 'publish',
        'post_name' => 'ai-studio',
        'post_content' => $content,
    ], true);

    if (!is_wp_error($post_id)) {
        update_option('ai_gateway_studio_post_id', (int) $post_id);
    }
}

function ai_gateway_activate_studio() {
    ai_gateway_register_studio_post_types();
    ai_gateway_seed_studio_post();
    flush_rewrite_rules();
}

register_activation_hook(AI_GATEWAY_PLUGIN_FILE, 'ai_gateway_activate_studio');
