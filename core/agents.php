<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_register_post_type() {
    register_post_type(AI_GATEWAY_POST_TYPE, [
        'labels' => [
            'name' => 'Agents IA',
            'singular_name' => 'Agent IA',
        ],
        'public' => false,
        'show_ui' => false,
        'show_in_menu' => false,
        'supports' => ['title'],
        'capability_type' => 'post',
    ]);

    $meta_args = [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => false,
        'sanitize_callback' => 'sanitize_text_field',
    ];

    register_post_meta(AI_GATEWAY_POST_TYPE, 'model', $meta_args);
    register_post_meta(AI_GATEWAY_POST_TYPE, 'system_prompt', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => false,
        'sanitize_callback' => 'sanitize_textarea_field',
    ]);
    register_post_meta(AI_GATEWAY_POST_TYPE, 'input_fields', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => false,
        'sanitize_callback' => 'wp_kses_post',
    ]);
    register_post_meta(AI_GATEWAY_POST_TYPE, 'mcp_endpoint', $meta_args);
    register_post_meta(AI_GATEWAY_POST_TYPE, 'output_mode', $meta_args);
    register_post_meta(AI_GATEWAY_POST_TYPE, 'ollama_preset', $meta_args);
    register_post_meta(AI_GATEWAY_POST_TYPE, 'provider', $meta_args);
    register_post_meta(AI_GATEWAY_POST_TYPE, 'provider_source', $meta_args);
    register_post_meta(AI_GATEWAY_POST_TYPE, 'tools', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => false,
        'sanitize_callback' => 'wp_kses_post',
    ]);
    register_post_meta(AI_GATEWAY_POST_TYPE, 'enabled', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => false,
        'sanitize_callback' => 'sanitize_text_field',
    ]);
}

add_action('init', 'ai_gateway_register_post_type');

function ai_gateway_get_agents($only_enabled = true) {
    $args = [
        'post_type' => AI_GATEWAY_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ];

    if ($only_enabled) {
        $args['meta_query'] = [
            [
                'key' => 'enabled',
                'value' => '1',
                'compare' => '=',
            ],
        ];
    }

    $posts = get_posts($args);
    $agents = [];

    foreach ($posts as $post) {
        $agents[] = ai_gateway_format_agent($post);
    }

    return $agents;
}

function ai_gateway_get_agent($agent_id) {
    $post = get_post($agent_id);
    if (!$post || $post->post_type !== AI_GATEWAY_POST_TYPE) {
        return null;
    }

    return ai_gateway_format_agent($post);
}

function ai_gateway_format_agent($post) {
    $input_fields = get_post_meta($post->ID, 'input_fields', true);
    $decoded_fields = [];
    if ($input_fields) {
        $decoded = json_decode($input_fields, true);
        if (is_array($decoded)) {
            $decoded_fields = $decoded;
        }
    }

    $tools_raw = get_post_meta($post->ID, 'tools', true);
    $tools = [];
    if ($tools_raw) {
        $decoded = json_decode($tools_raw, true);
        if (is_array($decoded)) {
            $tools = $decoded;
        }
    }
    if (empty($tools)) {
        $tools = array_keys(ai_gateway_get_tool_definitions());
    }

    $decoded_fields = ai_gateway_normalize_input_schema($decoded_fields);

    return [
        'id' => $post->ID,
        'name' => $post->post_title,
        'model' => get_post_meta($post->ID, 'model', true),
        'system_prompt' => get_post_meta($post->ID, 'system_prompt', true),
        'input_schema' => $decoded_fields,
        'mcp_endpoint' => get_post_meta($post->ID, 'mcp_endpoint', true),
        'output_mode' => get_post_meta($post->ID, 'output_mode', true) ?: 'text',
        'ollama_preset' => get_post_meta($post->ID, 'ollama_preset', true),
        'provider' => get_post_meta($post->ID, 'provider', true),
        'provider_source' => get_post_meta($post->ID, 'provider_source', true),
        'tools' => $tools,
        'enabled' => get_post_meta($post->ID, 'enabled', true) === '1',
    ];
}

function ai_gateway_update_agent_meta($agent_id, $params) {
    $fields = [
        'model' => 'model',
        'system_prompt' => 'system_prompt',
        'mcp_endpoint' => 'mcp_endpoint',
        'output_mode' => 'output_mode',
    ];

    foreach ($fields as $param => $meta_key) {
        if (isset($params[$param])) {
            $value = $params[$param];
            if ($param === 'mcp_endpoint') {
                $value = esc_url_raw($value);
            } else {
                $value = sanitize_text_field($value);
            }
            update_post_meta($agent_id, $meta_key, $value);
        }
    }

    if (isset($params['system_prompt'])) {
        update_post_meta($agent_id, 'system_prompt', sanitize_textarea_field($params['system_prompt']));
    }

    if (isset($params['input_schema'])) {
        $schema = is_array($params['input_schema']) ? $params['input_schema'] : [];
        update_post_meta($agent_id, 'input_fields', wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    if (isset($params['ollama_preset'])) {
        update_post_meta($agent_id, 'ollama_preset', sanitize_text_field($params['ollama_preset']));
    }

    if (isset($params['provider'])) {
        update_post_meta($agent_id, 'provider', sanitize_text_field($params['provider']));
    }

    if (isset($params['provider_source'])) {
        update_post_meta($agent_id, 'provider_source', sanitize_text_field($params['provider_source']));
    }

    if (isset($params['tools'])) {
        $tools = is_array($params['tools']) ? array_values($params['tools']) : [];
        update_post_meta($agent_id, 'tools', wp_json_encode($tools, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    if (isset($params['enabled'])) {
        update_post_meta($agent_id, 'enabled', $params['enabled'] ? '1' : '0');
    }
}

function ai_gateway_get_tool_definitions() {
    return [
        'smart_edit' => 'Smart Edit',
        'outline_sections' => 'Outline to Sections',
        'page_template' => 'Page Template',
        'faq_builder' => 'FAQ Builder',
        'cta_builder' => 'CTA Builder',
        'quick_palette' => 'Quick Palette',
        'media_insert' => 'Media Smart Insert',
        'hero_section' => 'Hero Section',
    ];
}

function ai_gateway_get_default_agents_seed() {
    return [
        [
            'name' => 'SEO',
            'model' => 'mistral:latest',
            'system_prompt' => 'You are an SEO expert. Analyze keywords, check content coherence, and propose concrete improvements.',
            'input_schema' => ['title', 'keyword', 'language', 'tone', 'audience', 'content'],
            'mcp_endpoint' => '',
            'output_mode' => 'text',
            'tools' => ['smart_edit', 'outline_sections', 'faq_builder', 'quick_palette'],
            'enabled' => true,
        ],
        [
            'name' => 'Writer',
            'model' => 'ministral-3:latest',
            'system_prompt' => 'You are a blog writer. Produce a clear, structured article with headings and a conclusion. Follow the requested length, language, and tone.',
            'input_schema' => ['topic', 'word_count', 'language', 'tone', 'audience', 'outline'],
            'mcp_endpoint' => '',
            'output_mode' => 'text',
            'tools' => ['smart_edit', 'outline_sections', 'faq_builder', 'cta_builder', 'hero_section'],
            'enabled' => true,
        ],
        [
            'name' => 'UI Changer',
            'model' => 'qwen2.5-coder:1.5b',
            'system_prompt' => 'You are a UI/UX assistant. Suggest visual improvements, layout tweaks, and clear UI changes in plain language.',
            'input_schema' => ['goal', 'current_ui', 'style', 'constraints'],
            'mcp_endpoint' => '',
            'output_mode' => 'text',
            'tools' => ['smart_edit', 'page_template', 'quick_palette', 'media_insert', 'hero_section'],
            'enabled' => true,
        ],
        [
            'name' => 'WP Builder',
            'model' => 'qwen2.5-coder:1.5b',
            'system_prompt' => 'You generate Gutenberg blocks as JSON. Return only JSON blocks. Use valid block names and attributes.',
            'input_schema' => ['page_type', 'goal', 'palette', 'sections', 'constraints'],
            'mcp_endpoint' => '',
            'output_mode' => 'blocks',
            'tools' => array_keys(ai_gateway_get_tool_definitions()),
            'enabled' => true,
        ],
    ];
}

function ai_gateway_seed_default_agents() {
    $existing = get_posts([
        'post_type' => AI_GATEWAY_POST_TYPE,
        'post_status' => 'any',
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);

    if (!empty($existing)) {
        return;
    }

    $defaults = ai_gateway_get_default_agents_seed();
    foreach ($defaults as $agent) {
        $post_id = wp_insert_post([
            'post_title' => $agent['name'],
            'post_type' => AI_GATEWAY_POST_TYPE,
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($post_id)) {
            continue;
        }

        update_post_meta($post_id, 'model', $agent['model']);
        update_post_meta($post_id, 'system_prompt', $agent['system_prompt']);
        update_post_meta(
            $post_id,
            'input_fields',
            wp_json_encode($agent['input_schema'], JSON_PRETTY_PRINT)
        );
        update_post_meta($post_id, 'mcp_endpoint', $agent['mcp_endpoint']);
        update_post_meta($post_id, 'output_mode', $agent['output_mode']);
        update_post_meta(
            $post_id,
            'tools',
            wp_json_encode($agent['tools'], JSON_PRETTY_PRINT)
        );
        update_post_meta($post_id, 'enabled', $agent['enabled'] ? '1' : '0');
    }
}

function ai_gateway_activate_plugin() {
    ai_gateway_register_post_type();
    ai_gateway_seed_default_agents();
    flush_rewrite_rules();
}

register_activation_hook(AI_GATEWAY_PLUGIN_FILE, 'ai_gateway_activate_plugin');
