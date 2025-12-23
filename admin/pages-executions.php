<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_render_executions_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $per_page = 20;
    $query = new WP_Query([
        'post_type' => 'ai_execution',
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'paged' => $paged,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    $retention_days = ai_gateway_get_logs_retention_days();
    ?>
    <div class="wrap">
        <h1>Executions</h1>
        <p>Conservation automatique: <?php echo esc_html($retention_days); ?> jours.</p>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Agent</th>
                    <th>Provider</th>
                    <th>Modele</th>
                    <th>Status</th>
                    <th>Duree</th>
                    <th>Output</th>
                    <th>Erreur</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$query->have_posts()): ?>
                    <tr><td colspan="9">Aucune execution.</td></tr>
                <?php else: ?>
                    <?php while ($query->have_posts()): $query->the_post(); ?>
                        <?php
                            $post_id = get_the_ID();
                            $agent_name = get_post_meta($post_id, 'agent_name', true);
                            $provider = get_post_meta($post_id, 'provider', true);
                            $model = get_post_meta($post_id, 'model', true);
                            $status = get_post_meta($post_id, 'status', true);
                            $duration = (int) get_post_meta($post_id, 'duration_ms', true);
                            $output_preview = get_post_meta($post_id, 'output_preview', true);
                            $error = get_post_meta($post_id, 'error', true);
                        ?>
                        <tr>
                            <td><?php echo esc_html(get_the_date('Y-m-d H:i')); ?></td>
                            <td><?php echo esc_html($agent_name ?: get_the_title()); ?></td>
                            <td><?php echo esc_html($provider); ?></td>
                            <td><?php echo esc_html($model); ?></td>
                            <td><?php echo esc_html($status); ?></td>
                            <td><?php echo esc_html($duration ? $duration . ' ms' : ''); ?></td>
                            <td><?php echo esc_html($output_preview); ?></td>
                            <td><?php echo esc_html($error); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('ai_gateway_download_execution'); ?>
                                    <input type="hidden" name="action" value="ai_gateway_download_execution" />
                                    <input type="hidden" name="execution_id" value="<?php echo esc_attr($post_id); ?>" />
                                    <button type="submit" class="button button-secondary">Telecharger</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        $total_pages = (int) $query->max_num_pages;
        if ($total_pages > 1):
            $base = esc_url(add_query_arg('paged', '%#%'));
        ?>
            <div class="tablenav">
                <?php
                echo paginate_links([
                    'base' => $base,
                    'format' => '',
                    'current' => $paged,
                    'total' => $total_pages,
                ]);
                ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
