<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_render_settings_page() {
    $ollama_url = ai_gateway_get_ollama_url();
    ?>
    <div class="wrap">
        <h1>Reglages</h1>
        <?php if (isset($_GET['settings-updated'])): ?>
            <div class="updated notice"><p>Reglages enregistres.</p></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ai_gateway_settings'); ?>
            <input type="hidden" name="action" value="ai_gateway_save_settings" />
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ollama_url">URL Ollama</label></th>
                    <td><input name="ollama_url" id="ollama_url" type="text" class="regular-text" value="<?php echo esc_attr($ollama_url); ?>" /></td>
                </tr>
            </table>
            <?php submit_button('Enregistrer'); ?>
        </form>
    </div>
    <?php
}