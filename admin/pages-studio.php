<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_render_studio_admin_page() {
    if (!current_user_can(ai_gateway_get_studio_capability())) {
        wp_die('Unauthorized');
    }

    $post_id = ai_gateway_get_studio_post_id();
    if (!$post_id) {
        ai_gateway_seed_studio_post();
        $post_id = ai_gateway_get_studio_post_id();
    }

    if (!$post_id) {
        echo '<div class="wrap"><p>AI Studio page not found.</p></div>';
        return;
    }

    $post = get_post($post_id);
    $content = apply_filters('the_content', $post->post_content);
    ?>
    <div class="ai-studio-admin">
        <?php echo $content; ?>
    </div>
    <?php
}
