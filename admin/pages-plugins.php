<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_render_plugins_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    $plugins = get_plugins();
    ?>
    <div class="wrap">
        <h1>Plugins IA</h1>
        <?php if (isset($_GET['updated'])): ?>
            <div class="updated notice"><p>Action effectuee.</p></div>
        <?php endif; ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Plugin</th>
                    <th>Version</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plugins as $file => $data): ?>
                    <?php $active = is_plugin_active($file); ?>
                    <tr>
                        <td><?php echo esc_html($data['Name']); ?></td>
                        <td><?php echo esc_html($data['Version']); ?></td>
                        <td><?php echo $active ? 'Active' : 'Inactive'; ?></td>
                        <td>
                            <?php if ($active): ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <?php wp_nonce_field('ai_gateway_plugin_action'); ?>
                                    <input type="hidden" name="action" value="ai_gateway_deactivate_plugin" />
                                    <input type="hidden" name="plugin_file" value="<?php echo esc_attr($file); ?>" />
                                    <button type="submit" class="button">Deactivate</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <?php wp_nonce_field('ai_gateway_plugin_action'); ?>
                                    <input type="hidden" name="action" value="ai_gateway_activate_plugin" />
                                    <input type="hidden" name="plugin_file" value="<?php echo esc_attr($file); ?>" />
                                    <button type="submit" class="button button-primary">Activate</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}