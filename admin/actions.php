<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_ai_gateway_save_settings', 'ai_gateway_save_settings');
add_action('admin_post_ai_gateway_save_agent', 'ai_gateway_save_agent');
add_action('admin_post_ai_gateway_delete_agent', 'ai_gateway_delete_agent');
add_action('admin_post_ai_gateway_check_updates', 'ai_gateway_check_updates');
add_action('admin_post_ai_gateway_export_agents', 'ai_gateway_export_agents');
add_action('admin_post_ai_gateway_import_agents', 'ai_gateway_import_agents');

function ai_gateway_save_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ai_gateway_settings');

    $ollama_url = isset($_POST['ollama_url']) ? esc_url_raw(wp_unslash($_POST['ollama_url'])) : '';
    $preset = isset($_POST['ollama_preset']) ? sanitize_text_field(wp_unslash($_POST['ollama_preset'])) : 'balanced';
    $provider_default = isset($_POST['provider_default']) ? sanitize_text_field(wp_unslash($_POST['provider_default'])) : 'ollama';
    $provider_source_default = isset($_POST['provider_source_default']) ? sanitize_text_field(wp_unslash($_POST['provider_source_default'])) : 'openrouter';
    $logs_retention = isset($_POST['logs_retention_days']) ? absint($_POST['logs_retention_days']) : 30;

    $int_fields = [
        'ai_gateway_ollama_num_predict' => 'ollama_num_predict',
        'ai_gateway_ollama_num_ctx' => 'ollama_num_ctx',
        'ai_gateway_ollama_num_gpu' => 'ollama_num_gpu',
        'ai_gateway_ollama_num_thread' => 'ollama_num_thread',
        'ai_gateway_ollama_top_k' => 'ollama_top_k',
        'ai_gateway_ollama_seed' => 'ollama_seed',
    ];

    $float_fields = [
        'ai_gateway_ollama_temperature' => 'ollama_temperature',
        'ai_gateway_ollama_top_p' => 'ollama_top_p',
        'ai_gateway_ollama_repeat_penalty' => 'ollama_repeat_penalty',
    ];
    if ($ollama_url !== '') {
        update_option('ai_gateway_ollama_url', $ollama_url);
    }

    if (!in_array($preset, ['speed', 'balanced', 'quality'], true)) {
        $preset = 'balanced';
    }
    update_option('ai_gateway_ollama_preset', $preset);

    $providers = ai_gateway_get_provider_list();
    if (!isset($providers[$provider_default])) {
        $provider_default = 'ollama';
    }
    update_option('ai_gateway_provider_default', $provider_default);

    $sources = ai_gateway_get_openai_compat_sources();
    if (!isset($sources[$provider_source_default])) {
        $provider_source_default = 'openrouter';
    }
    update_option('ai_gateway_openai_compat_source', $provider_source_default);

    if ($logs_retention <= 0) {
        $logs_retention = 30;
    }
    update_option('ai_gateway_logs_retention_days', $logs_retention);

    foreach ($int_fields as $option => $field) {
        $raw = isset($_POST[$field]) ? wp_unslash($_POST[$field]) : '';
        $raw = trim((string) $raw);
        if ($raw === '') {
            delete_option($option);
            continue;
        }
        update_option($option, (int) $raw);
    }

    foreach ($float_fields as $option => $field) {
        $raw = isset($_POST[$field]) ? wp_unslash($_POST[$field]) : '';
        $raw = trim((string) $raw);
        if ($raw === '') {
            delete_option($option);
            continue;
        }
        update_option($option, (float) $raw);
    }

    $presets_input = isset($_POST['ollama_presets']) && is_array($_POST['ollama_presets'])
        ? (array) wp_unslash($_POST['ollama_presets'])
        : [];
    $presets_delete = isset($_POST['ollama_presets_delete'])
        ? array_map('sanitize_text_field', (array) wp_unslash($_POST['ollama_presets_delete']))
        : [];
    $new_preset = isset($_POST['ollama_preset_new']) && is_array($_POST['ollama_preset_new'])
        ? (array) wp_unslash($_POST['ollama_preset_new'])
        : [];

    $fields = ['num_predict','num_ctx','num_gpu','num_thread','temperature','top_p','top_k','repeat_penalty','seed'];
    $presets = [];

    foreach ($presets_input as $preset_id => $data) {
        $preset_id = sanitize_text_field($preset_id);
        if (in_array($preset_id, $presets_delete, true)) {
            continue;
        }
        $label = isset($data['label']) ? sanitize_text_field($data['label']) : $preset_id;
        if ($label === '') {
            continue;
        }
        $options = [];
        foreach ($fields as $key) {
            if (!isset($data[$key]) || $data[$key] === '') {
                continue;
            }
            $options[$key] = is_numeric($data[$key]) ? (float) $data[$key] : $data[$key];
        }
        $presets[$preset_id] = [
            'label' => $label,
            'options' => $options,
        ];
    }

    $new_label = isset($new_preset['label']) ? sanitize_text_field($new_preset['label']) : '';
    if ($new_label !== '') {
        $new_id = sanitize_title($new_label);
        if ($new_id === '') {
            $new_id = 'preset_' . time();
        }
        $options = [];
        foreach ($fields as $key) {
            if (!isset($new_preset[$key]) || $new_preset[$key] === '') {
                continue;
            }
            $options[$key] = is_numeric($new_preset[$key]) ? (float) $new_preset[$key] : $new_preset[$key];
        }
        $presets[$new_id] = [
            'label' => $new_label,
            'options' => $options,
        ];
    }

    if (!empty($presets)) {
        update_option('ai_gateway_ollama_presets', $presets);
    } else {
        delete_option('ai_gateway_ollama_presets');
    }

    wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
    exit;
}

function ai_gateway_check_updates() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ai_gateway_check_updates');

    delete_transient('ai_gateway_update_info');
    wp_update_plugins();

    wp_redirect(admin_url('admin.php?page=ai-gateway-settings&updated=1'));
    exit;
}

function ai_gateway_save_agent() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ai_gateway_save_agent');

    $agent_id = isset($_POST['agent_id']) ? absint($_POST['agent_id']) : 0;
    $name = isset($_POST['agent_name']) ? sanitize_text_field(wp_unslash($_POST['agent_name'])) : '';
    $model = isset($_POST['agent_model']) ? sanitize_text_field(wp_unslash($_POST['agent_model'])) : '';
    $system_prompt = isset($_POST['agent_system_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['agent_system_prompt'])) : '';
    $input_schema_raw = isset($_POST['agent_input_schema']) ? wp_unslash($_POST['agent_input_schema']) : '';
    $fields_input = isset($_POST['agent_fields']) && is_array($_POST['agent_fields'])
        ? (array) wp_unslash($_POST['agent_fields'])
        : [];
    $mcp_endpoint = isset($_POST['agent_mcp_endpoint']) ? esc_url_raw(wp_unslash($_POST['agent_mcp_endpoint'])) : '';
    $output_mode = isset($_POST['agent_output_mode']) ? sanitize_text_field(wp_unslash($_POST['agent_output_mode'])) : 'text';
    $ollama_preset = isset($_POST['agent_ollama_preset']) ? sanitize_text_field(wp_unslash($_POST['agent_ollama_preset'])) : '';
    $provider = isset($_POST['agent_provider']) ? sanitize_text_field(wp_unslash($_POST['agent_provider'])) : '';
    $provider_source = isset($_POST['agent_provider_source']) ? sanitize_text_field(wp_unslash($_POST['agent_provider_source'])) : '';
    $tools = isset($_POST['agent_tools']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['agent_tools'])) : [];
    $enabled = isset($_POST['agent_enabled']) ? '1' : '0';

    $input_schema = [];
    if (!empty($fields_input)) {
        foreach ($fields_input as $field) {
            if (!is_array($field)) {
                continue;
            }
            $key = isset($field['key']) ? sanitize_key($field['key']) : '';
            $label = isset($field['label']) ? sanitize_text_field($field['label']) : '';
            $type = isset($field['type']) ? sanitize_text_field($field['type']) : 'text';
            if (!in_array($type, ['text', 'textarea', 'number', 'url', 'select', 'password'], true)) {
                $type = 'text';
            }
            $placeholder = isset($field['placeholder']) ? sanitize_text_field($field['placeholder']) : '';
            $required = !empty($field['required']);
            $env = isset($field['env']) ? sanitize_text_field($field['env']) : '';
            $options_raw = isset($field['options']) ? (string) $field['options'] : '';
            $options = [];
            if ($options_raw !== '') {
                $parts = preg_split('/[,\n]/', $options_raw);
                foreach ($parts as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $options[] = $part;
                    }
                }
            }
            if ($key === '') {
                continue;
            }
            $input_schema[] = [
                'key' => $key,
                'label' => $label ?: $key,
                'type' => $type,
                'required' => $required,
                'placeholder' => $placeholder,
                'options' => $options,
                'env' => $env,
            ];
        }
    } elseif ($input_schema_raw) {
        $decoded = json_decode($input_schema_raw, true);
        if (is_array($decoded)) {
            $input_schema = $decoded;
        }
    }

    $post_data = [
        'post_title' => $name,
        'post_type' => AI_GATEWAY_POST_TYPE,
        'post_status' => 'publish',
    ];

    if ($agent_id) {
        $post_data['ID'] = $agent_id;
        $agent_id = wp_update_post($post_data, true);
    } else {
        $agent_id = wp_insert_post($post_data, true);
    }

    if (is_wp_error($agent_id)) {
        wp_die($agent_id->get_error_message());
    }

    update_post_meta($agent_id, 'model', $model);
    update_post_meta($agent_id, 'system_prompt', $system_prompt);
    update_post_meta($agent_id, 'input_fields', wp_json_encode($input_schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    update_post_meta($agent_id, 'mcp_endpoint', $mcp_endpoint);
    update_post_meta($agent_id, 'output_mode', $output_mode);
    update_post_meta($agent_id, 'ollama_preset', $ollama_preset);
    update_post_meta($agent_id, 'provider', $provider);
    update_post_meta($agent_id, 'provider_source', $provider_source);
    update_post_meta($agent_id, 'tools', wp_json_encode($tools, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    update_post_meta($agent_id, 'enabled', $enabled);

    wp_redirect(admin_url('admin.php?page=ai-gateway-agents&updated=1'));
    exit;
}

function ai_gateway_delete_agent() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ai_gateway_delete_agent');

    $agent_id = isset($_POST['agent_id']) ? absint($_POST['agent_id']) : 0;
    if ($agent_id) {
        wp_trash_post($agent_id);
    }

    wp_redirect(admin_url('admin.php?page=ai-gateway-agents&deleted=1'));
    exit;
}

function ai_gateway_export_agents() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ai_gateway_export_agents');

    $agents = ai_gateway_get_agents(false);
    $payload = [
        'version' => AI_GATEWAY_VERSION,
        'exported_at' => gmdate('c'),
        'agents' => $agents,
    ];

    $filename = 'ai-gateway-pack-' . gmdate('Ymd-His') . '.json';
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    echo wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function ai_gateway_import_agents() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ai_gateway_import_agents');

    if (empty($_FILES['agents_pack']['tmp_name'])) {
        wp_redirect(admin_url('admin.php?page=ai-gateway-agents&imported=0'));
        exit;
    }

    $content = file_get_contents($_FILES['agents_pack']['tmp_name']);
    $decoded = json_decode($content, true);
    if (!is_array($decoded) || empty($decoded['agents']) || !is_array($decoded['agents'])) {
        wp_redirect(admin_url('admin.php?page=ai-gateway-agents&imported=0'));
        exit;
    }

    $created = 0;
    foreach ($decoded['agents'] as $agent) {
        if (!is_array($agent) || empty($agent['name'])) {
            continue;
        }
        $name = sanitize_text_field($agent['name']);
        $existing = get_page_by_title($name, OBJECT, AI_GATEWAY_POST_TYPE);
        if ($existing) {
            $name .= ' (imported)';
        }

        $post_id = wp_insert_post([
            'post_title' => $name,
            'post_type' => AI_GATEWAY_POST_TYPE,
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($post_id)) {
            continue;
        }

        ai_gateway_update_agent_meta($post_id, $agent);
        if (isset($agent['tools']) && is_array($agent['tools'])) {
            update_post_meta($post_id, 'tools', wp_json_encode($agent['tools'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        if (isset($agent['ollama_preset'])) {
            update_post_meta($post_id, 'ollama_preset', sanitize_text_field($agent['ollama_preset']));
        }
        if (isset($agent['enabled'])) {
            update_post_meta($post_id, 'enabled', $agent['enabled'] ? '1' : '0');
        }
        $created++;
    }

    wp_redirect(admin_url('admin.php?page=ai-gateway-agents&imported=' . $created));
    exit;
}
