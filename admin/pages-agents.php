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
        $agent = $agent_id ? ai_gateway_get_agent($agent_id) : null;
        $name = $agent['name'] ?? '';
        $model = $agent['model'] ?? '';
        $system_prompt = $agent['system_prompt'] ?? '';
        $input_schema = $agent['input_schema'] ?? [];
        $mcp_endpoint = $agent['mcp_endpoint'] ?? '';
        $output_mode = $agent['output_mode'] ?? 'text';
        $ollama_preset = $agent['ollama_preset'] ?? '';
        $enabled = $agent['enabled'] ?? true;
        $tools = $agent['tools'] ?? array_keys(ai_gateway_get_tool_definitions());
        $input_json = wp_json_encode($input_schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $presets = ai_gateway_get_ollama_presets();
        $global_preset = ai_gateway_get_ollama_preset();
        $global_label = isset($presets[$global_preset]['label']) ? $presets[$global_preset]['label'] : $global_preset;
        ?>
        <div class="wrap">
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
                        <th scope="row"><label for="agent_system_prompt">System prompt</label></th>
                        <td><textarea name="agent_system_prompt" id="agent_system_prompt" rows="6" class="large-text code"><?php echo esc_textarea($system_prompt); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_input_schema">Input fields (JSON)</label></th>
                        <td><textarea name="agent_input_schema" id="agent_input_schema" rows="6" class="large-text code"><?php echo esc_textarea($input_json); ?></textarea></td>
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
        <?php
        return;
    }

    $agents = ai_gateway_get_agents(false);
    ?>
    <div class="wrap">
        <h1>Agents IA</h1>
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
