<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_render_settings_page() {
    $ollama_url = ai_gateway_get_ollama_url();
    $preset = ai_gateway_get_ollama_preset();
    $num_predict = get_option('ai_gateway_ollama_num_predict', '');
    $num_ctx = get_option('ai_gateway_ollama_num_ctx', '');
    $num_gpu = get_option('ai_gateway_ollama_num_gpu', '');
    $num_thread = get_option('ai_gateway_ollama_num_thread', '');
    $temperature = get_option('ai_gateway_ollama_temperature', '');
    $top_p = get_option('ai_gateway_ollama_top_p', '');
    $top_k = get_option('ai_gateway_ollama_top_k', '');
    $repeat_penalty = get_option('ai_gateway_ollama_repeat_penalty', '');
    $seed = get_option('ai_gateway_ollama_seed', '');
    $presets = ai_gateway_get_ollama_presets();
    ?>
    <div class="wrap">
        <h1>Reglages</h1>
        <?php if (isset($_GET['settings-updated'])): ?>
            <div class="updated notice"><p>Reglages enregistres.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['updated'])): ?>
            <div class="updated notice"><p>Verification des mises a jour declenchee.</p></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ai_gateway_settings'); ?>
            <input type="hidden" name="action" value="ai_gateway_save_settings" />
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ollama_url">URL Ollama</label></th>
                    <td><input name="ollama_url" id="ollama_url" type="text" class="regular-text" value="<?php echo esc_attr($ollama_url); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ollama_preset">Preset Ollama</label></th>
                    <td>
                        <select name="ollama_preset" id="ollama_preset">
                            <option value="speed" <?php selected($preset, 'speed'); ?>>Rapidite</option>
                            <option value="balanced" <?php selected($preset, 'balanced'); ?>>Equilibre</option>
                            <option value="quality" <?php selected($preset, 'quality'); ?>>Qualite</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button('Enregistrer'); ?>
        </form>
        <h2>Parametres avances Ollama</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ai_gateway_settings'); ?>
            <input type="hidden" name="action" value="ai_gateway_save_settings" />
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ollama_num_predict">Tokens max (num_predict)</label></th>
                    <td><input name="ollama_num_predict" id="ollama_num_predict" type="number" class="regular-text" value="<?php echo esc_attr($num_predict); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ollama_num_ctx">Context window (num_ctx)</label></th>
                    <td><input name="ollama_num_ctx" id="ollama_num_ctx" type="number" class="regular-text" value="<?php echo esc_attr($num_ctx); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ollama_num_gpu">GPU layers (num_gpu)</label></th>
                    <td><input name="ollama_num_gpu" id="ollama_num_gpu" type="number" class="regular-text" value="<?php echo esc_attr($num_gpu); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ollama_num_thread">Threads (num_thread)</label></th>
                    <td><input name="ollama_num_thread" id="ollama_num_thread" type="number" class="regular-text" value="<?php echo esc_attr($num_thread); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ollama_temperature">Temperature</label></th>
                    <td><input name="ollama_temperature" id="ollama_temperature" type="number" step="0.01" class="regular-text" value="<?php echo esc_attr($temperature); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ollama_top_p">Top P</label></th>
                    <td><input name="ollama_top_p" id="ollama_top_p" type="number" step="0.01" class="regular-text" value="<?php echo esc_attr($top_p); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ollama_top_k">Top K</label></th>
                    <td><input name="ollama_top_k" id="ollama_top_k" type="number" class="regular-text" value="<?php echo esc_attr($top_k); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ollama_repeat_penalty">Repeat penalty</label></th>
                    <td><input name="ollama_repeat_penalty" id="ollama_repeat_penalty" type="number" step="0.01" class="regular-text" value="<?php echo esc_attr($repeat_penalty); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ollama_seed">Seed</label></th>
                    <td><input name="ollama_seed" id="ollama_seed" type="number" class="regular-text" value="<?php echo esc_attr($seed); ?>" /></td>
                </tr>
            </table>
            <details style="margin-top:16px;">
                <summary><strong>Presets avances</strong></summary>
                <p>Ces presets sont globaux. Un preset choisi sur un agent ecrase le preset global.</p>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Preset</th>
                            <th>Options</th>
                            <th>Supprimer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($presets as $preset_id => $preset_data): ?>
                            <tr>
                                <td>
                                    <input type="text" name="ollama_presets[<?php echo esc_attr($preset_id); ?>][label]" value="<?php echo esc_attr($preset_data['label'] ?? $preset_id); ?>" />
                                </td>
                                <td>
                                    <?php
                                    $opts = $preset_data['options'] ?? [];
                                    $fields = [
                                        'num_predict' => 'num_predict',
                                        'num_ctx' => 'num_ctx',
                                        'num_gpu' => 'num_gpu',
                                        'num_thread' => 'num_thread',
                                        'temperature' => 'temperature',
                                        'top_p' => 'top_p',
                                        'top_k' => 'top_k',
                                        'repeat_penalty' => 'repeat_penalty',
                                        'seed' => 'seed',
                                    ];
                                    foreach ($fields as $key => $label):
                                        $value = isset($opts[$key]) ? $opts[$key] : '';
                                    ?>
                                        <label style="display:inline-block; margin-right:12px; margin-bottom:6px;">
                                            <?php echo esc_html($label); ?>
                                            <input type="text" name="ollama_presets[<?php echo esc_attr($preset_id); ?>][<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>" style="width:90px;" />
                                        </label>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ollama_presets_delete[]" value="<?php echo esc_attr($preset_id); ?>" />
                                        Supprimer
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <h3>Ajouter un preset</h3>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ollama_preset_new_label">Nom</label></th>
                        <td><input type="text" id="ollama_preset_new_label" name="ollama_preset_new[label]" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Options</th>
                        <td>
                            <?php foreach (['num_predict','num_ctx','num_gpu','num_thread','temperature','top_p','top_k','repeat_penalty','seed'] as $key): ?>
                                <label style="display:inline-block; margin-right:12px; margin-bottom:6px;">
                                    <?php echo esc_html($key); ?>
                                    <input type="text" name="ollama_preset_new[<?php echo esc_attr($key); ?>]" style="width:90px;" />
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
            </details>
            <?php submit_button('Enregistrer'); ?>
        </form>
        <hr />
        <h2>Mises a jour</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ai_gateway_check_updates'); ?>
            <input type="hidden" name="action" value="ai_gateway_check_updates" />
            <?php submit_button('Verifier les mises a jour', 'secondary'); ?>
        </form>
    </div>
    <?php
}
