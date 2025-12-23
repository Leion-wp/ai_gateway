<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_render_agents_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
    $agent_id = isset($_GET['agent_id']) ? absint($_GET['agent_id']) : 0;

    if ($action === 'edit' || $action === 'new') {
        $studio = !empty($_GET['studio']);
        $agent = $agent_id ? ai_gateway_get_agent($agent_id) : null;
        $name = $agent['name'] ?? '';
        $model = $agent['model'] ?? '';
        $system_prompt = $agent['system_prompt'] ?? '';
        $input_schema = $agent['input_schema'] ?? [];
        $mcp_endpoint = $agent['mcp_endpoint'] ?? '';
        $output_mode = $agent['output_mode'] ?? 'text';
        $ollama_preset = $agent['ollama_preset'] ?? '';
        $provider = $agent['provider'] ?? '';
        $provider_source = $agent['provider_source'] ?? '';
        $enabled = $agent['enabled'] ?? true;
        $tools = $agent['tools'] ?? array_keys(ai_gateway_get_tool_definitions());
        $presets = ai_gateway_get_ollama_presets();
        $global_preset = ai_gateway_get_ollama_preset();
        $global_label = isset($presets[$global_preset]['label']) ? $presets[$global_preset]['label'] : $global_preset;
        $providers = ai_gateway_get_provider_list();
        $provider_sources = ai_gateway_get_openai_compat_sources();
        $input_schema = ai_gateway_normalize_input_schema($input_schema);
        ?>
        <div class="wrap">
            <?php if ($studio): ?>
                <style>
                    #wpadminbar, #adminmenuwrap, #adminmenuback, #wpfooter { display: none; }
                    #wpcontent { margin-left: 0 !important; padding-left: 0 !important; }
                    #wpbody-content { padding-top: 0 !important; }
                    body { background: #0f1115; color: #e5e7eb; }
                    .wrap { background: #111318; border: 1px solid #262b36; border-radius: 14px; padding: 24px; }
                    .form-table th, .form-table td { color: #e5e7eb; }
                    input, select, textarea { background: #0f1115; color: #e5e7eb; border-color: #2c313c; }
                </style>
            <?php endif; ?>
            <h1><?php echo $action === 'new' ? 'Ajouter un agent' : 'Modifier un agent'; ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ai_gateway_save_agent'); ?>
                <input type="hidden" name="action" value="ai_gateway_save_agent" />
                <input type="hidden" name="agent_id" value="<?php echo esc_attr($agent_id); ?>" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="agent_name">Nom</label></th>
                        <td><input name="agent_name" id="agent_name" type="text" class="regular-text" value="<?php echo esc_attr($name); ?>" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_model">Modele</label></th>
                        <td><input name="agent_model" id="agent_model" type="text" class="regular-text" value="<?php echo esc_attr($model); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_provider">Provider</label></th>
                        <td>
                            <select name="agent_provider" id="agent_provider">
                                <option value=""><?php echo esc_html('Global'); ?></option>
                                <?php foreach ($providers as $provider_id => $label): ?>
                                    <option value="<?php echo esc_attr($provider_id); ?>" <?php selected($provider, $provider_id); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_provider_source">OpenAI-compatible source</label></th>
                        <td>
                            <select name="agent_provider_source" id="agent_provider_source">
                                <option value=""><?php echo esc_html('Global'); ?></option>
                                <?php foreach ($provider_sources as $source_id => $source_data): ?>
                                    <option value="<?php echo esc_attr($source_id); ?>" <?php selected($provider_source, $source_id); ?>>
                                        <?php echo esc_html($source_data['label'] ?? $source_id); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Utilise seulement si le provider est OpenAI-compatible.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_system_prompt">System prompt</label></th>
                        <td><textarea name="agent_system_prompt" id="agent_system_prompt" rows="6" class="large-text code"><?php echo esc_textarea($system_prompt); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row">Input fields</th>
                        <td>
                            <table class="widefat fixed striped" id="ai-gateway-input-fields">
                                <thead>
                                    <tr>
                                        <th>Label</th>
                                        <th>Key</th>
                                        <th>Type</th>
                                        <th>Options</th>
                                        <th>Placeholder</th>
                                        <th>Required</th>
                                        <th>Env</th>
                                        <th>Supprimer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($input_schema)): ?>
                                        <?php $input_schema = [['key' => '', 'label' => '', 'type' => 'text', 'options' => [], 'placeholder' => '', 'required' => false, 'env' => '']]; ?>
                                    <?php endif; ?>
                                    <?php foreach ($input_schema as $index => $field): ?>
                                        <?php
                                            $options = isset($field['options']) && is_array($field['options']) ? implode(', ', $field['options']) : '';
                                            $type = $field['type'] ?? 'text';
                                        ?>
                                        <tr>
                                            <td><input type="text" name="agent_fields[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($field['label'] ?? ''); ?>" /></td>
                                            <td><input type="text" name="agent_fields[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($field['key'] ?? ''); ?>" /></td>
                                            <td>
                                                <select name="agent_fields[<?php echo esc_attr($index); ?>][type]">
                                                    <?php foreach (['text','textarea','number','url','select','password'] as $type_option): ?>
                                                        <option value="<?php echo esc_attr($type_option); ?>" <?php selected($type, $type_option); ?>>
                                                            <?php echo esc_html($type_option); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input type="text" name="agent_fields[<?php echo esc_attr($index); ?>][options]" value="<?php echo esc_attr($options); ?>" placeholder="opt1, opt2" /></td>
                                            <td><input type="text" name="agent_fields[<?php echo esc_attr($index); ?>][placeholder]" value="<?php echo esc_attr($field['placeholder'] ?? ''); ?>" /></td>
                                            <td><input type="checkbox" name="agent_fields[<?php echo esc_attr($index); ?>][required]" value="1" <?php checked(!empty($field['required'])); ?> /></td>
                                            <td><input type="text" name="agent_fields[<?php echo esc_attr($index); ?>][env]" value="<?php echo esc_attr($field['env'] ?? ''); ?>" placeholder="AI_GATEWAY_..." /></td>
                                            <td><button type="button" class="button ai-gateway-remove-field">Supprimer</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p><button type="button" class="button" id="ai-gateway-add-field">Ajouter un champ</button></p>
                            <p class="description">Options = valeurs separees par des virgules. Env = constante serveur.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_mcp_endpoint">MCP endpoint</label></th>
                        <td><input name="agent_mcp_endpoint" id="agent_mcp_endpoint" type="text" class="regular-text" value="<?php echo esc_attr($mcp_endpoint); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_output_mode">Output mode</label></th>
                        <td>
                            <select name="agent_output_mode" id="agent_output_mode">
                                <option value="text" <?php selected($output_mode, 'text'); ?>>Text</option>
                                <option value="blocks" <?php selected($output_mode, 'blocks'); ?>>Blocks</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_ollama_preset">Preset Ollama</label></th>
                        <td>
                            <select name="agent_ollama_preset" id="agent_ollama_preset">
                                <option value=""><?php echo esc_html('Global (' . $global_label . ')'); ?></option>
                                <?php foreach ($presets as $preset_id => $preset_data): ?>
                                    <option value="<?php echo esc_attr($preset_id); ?>" <?php selected($ollama_preset, $preset_id); ?>>
                                        <?php echo esc_html($preset_data['label'] ?? $preset_id); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Tools</th>
                        <td>
                            <?php foreach (ai_gateway_get_tool_definitions() as $tool_id => $label): ?>
                                <label style="display:block;">
                                    <input
                                        type="checkbox"
                                        name="agent_tools[]"
                                        value="<?php echo esc_attr($tool_id); ?>"
                                        <?php checked(in_array($tool_id, $tools, true)); ?>
                                    />
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_enabled">Enabled</label></th>
                        <td><input name="agent_enabled" id="agent_enabled" type="checkbox" value="1" <?php checked($enabled); ?> /></td>
                    </tr>
                </table>
                <?php submit_button('Enregistrer'); ?>
            </form>
        </div>
        <script>
            (function() {
                const table = document.getElementById('ai-gateway-input-fields');
                if (!table) {
                    return;
                }
                const addButton = document.getElementById('ai-gateway-add-field');
                const tbody = table.querySelector('tbody');

                const buildRow = (index) => {
                    return `
                        <tr>
                            <td><input type="text" name="agent_fields[${index}][label]" value="" /></td>
                            <td><input type="text" name="agent_fields[${index}][key]" value="" /></td>
                            <td>
                                <select name="agent_fields[${index}][type]">
                                    <option value="text">text</option>
                                    <option value="textarea">textarea</option>
                                    <option value="number">number</option>
                                    <option value="url">url</option>
                                    <option value="select">select</option>
                                    <option value="password">password</option>
                                </select>
                            </td>
                            <td><input type="text" name="agent_fields[${index}][options]" value="" placeholder="opt1, opt2" /></td>
                            <td><input type="text" name="agent_fields[${index}][placeholder]" value="" /></td>
                            <td><input type="checkbox" name="agent_fields[${index}][required]" value="1" /></td>
                            <td><input type="text" name="agent_fields[${index}][env]" value="" placeholder="AI_GATEWAY_..." /></td>
                            <td><button type="button" class="button ai-gateway-remove-field">Supprimer</button></td>
                        </tr>
                    `;
                };

                const removeHandler = (event) => {
                    if (!event.target.classList.contains('ai-gateway-remove-field')) {
                        return;
                    }
                    const row = event.target.closest('tr');
                    if (row) {
                        row.remove();
                    }
                };

                tbody.addEventListener('click', removeHandler);
                addButton.addEventListener('click', () => {
                    const index = tbody.querySelectorAll('tr').length;
                    tbody.insertAdjacentHTML('beforeend', buildRow(index));
                });
            })();
        </script>
        <?php
        return;
    }

    $agents = ai_gateway_get_agents(false);
    $imported = isset($_GET['imported']) ? absint($_GET['imported']) : null;
    ?>
    <div class="wrap">
        <h1>Agents IA</h1>
        <?php if ($imported !== null): ?>
            <div class="updated notice"><p><?php echo esc_html($imported); ?> agent(s) importes.</p></div>
        <?php endif; ?>
        <div style="margin-bottom:16px;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-right:12px;">
                <?php wp_nonce_field('ai_gateway_export_agents'); ?>
                <input type="hidden" name="action" value="ai_gateway_export_agents" />
                <?php submit_button('Exporter pack JSON', 'secondary', 'submit', false); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="display:inline-block;">
                <?php wp_nonce_field('ai_gateway_import_agents'); ?>
                <input type="hidden" name="action" value="ai_gateway_import_agents" />
                <input type="file" name="agents_pack" accept="application/json" />
                <?php submit_button('Importer pack JSON', 'secondary', 'submit', false); ?>
            </form>
        </div>
        <p><a href="<?php echo esc_url(admin_url('admin.php?page=ai-gateway-agents&action=new')); ?>" class="button button-primary">Ajouter</a></p>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Modele</th>
                    <th>Enabled</th>
                    <th>Output mode</th>
                    <th>MCP endpoint</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($agents)): ?>
                    <tr><td colspan="6">Aucun agent.</td></tr>
                <?php else: ?>
                    <?php foreach ($agents as $agent): ?>
                        <tr>
                            <td><?php echo esc_html($agent['name']); ?></td>
                            <td><?php echo esc_html($agent['model']); ?></td>
                            <td><?php echo $agent['enabled'] ? 'Oui' : 'Non'; ?></td>
                            <td><?php echo esc_html($agent['output_mode']); ?></td>
                            <td><?php echo esc_html($agent['mcp_endpoint']); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-gateway-agents&action=edit&agent_id=' . $agent['id'])); ?>">Modifier</a>
                                |
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <?php wp_nonce_field('ai_gateway_delete_agent'); ?>
                                    <input type="hidden" name="action" value="ai_gateway_delete_agent" />
                                    <input type="hidden" name="agent_id" value="<?php echo esc_attr($agent['id']); ?>" />
                                    <button type="submit" class="link-button">Archiver</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <style>
        .link-button {
            background: none;
            border: none;
            color: #2271b1;
            cursor: pointer;
            padding: 0;
        }
    </style>
    <?php
}
