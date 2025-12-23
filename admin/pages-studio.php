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
    <style>
        .ai-studio-admin { background: #0f1115; color: #e5e7eb; min-height: 100vh; padding: 24px; }
        .ai-studio-admin .ai-studio-app { display: grid; grid-template-columns: 280px 1fr 360px; gap: 16px; min-height: 80vh; }
        .ai-studio-admin .ai-studio-sidebar { background: #171a21; color: #e5e7eb; border-radius: 14px; padding: 18px; display: flex; flex-direction: column; gap: 16px; border: 1px solid #262b36; }
        .ai-studio-admin .ai-studio-sidebar-title { font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: #9ca3af; margin-bottom: 8px; }
        .ai-studio-admin .ai-studio-sidebar-section { display: flex; flex-direction: column; gap: 8px; }
        .ai-studio-admin .ai-studio-sidebar-empty { font-size: 13px; color: #9ca3af; }
        .ai-studio-admin .ai-studio-select { width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #2c313c; background: #1f2430; color: #e5e7eb; }
        .ai-studio-admin .ai-studio-conversation-list { display: flex; flex-direction: column; gap: 8px; }
        .ai-studio-admin .ai-studio-conversation-row { display: flex; gap: 8px; align-items: stretch; }
        .ai-studio-admin .ai-studio-conversation { flex: 1; text-align: left; background: #1f2430; color: #e5e7eb; border: 1px solid #2c313c; border-radius: 12px; padding: 10px; cursor: pointer; }
        .ai-studio-admin .ai-studio-conversation.active { border-color: #3b82f6; }
        .ai-studio-admin .ai-studio-conversation-title { font-size: 13px; font-weight: 600; }
        .ai-studio-admin .ai-studio-conversation-last { font-size: 12px; color: #9ca3af; margin-top: 4px; }
        .ai-studio-admin .ai-studio-main { background: #111318; border-radius: 14px; padding: 18px; display: flex; flex-direction: column; gap: 16px; border: 1px solid #262b36; }
        .ai-studio-admin .ai-studio-topbar { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border-radius: 12px; background: #171a21; border: 1px solid #262b36; color: #e5e7eb; }
        .ai-studio-admin .ai-studio-topbar-label { font-size: 12px; text-transform: uppercase; color: #9ca3af; margin-right: 8px; }
        .ai-studio-admin .ai-studio-topbar-value { font-weight: 600; }
        .ai-studio-admin .ai-studio-topbar-right { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #9ca3af; }
        .ai-studio-admin .ai-studio-status-dot { width: 8px; height: 8px; border-radius: 999px; background: #22c55e; display: inline-block; }
        .ai-studio-admin .ai-studio-chat { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding: 8px; border: 1px solid #262b36; border-radius: 14px; min-height: 320px; background: #0f1115; }
        .ai-studio-admin .ai-studio-message { padding: 12px; border-radius: 12px; background: #1a1f29; color: #e5e7eb; }
        .ai-studio-admin .ai-studio-message-role { font-size: 12px; text-transform: uppercase; color: #9ca3af; margin-bottom: 6px; }
        .ai-studio-admin .ai-studio-user { background: #1f2937; }
        .ai-studio-admin .ai-studio-assistant { background: #1a1f29; }
        .ai-studio-admin .ai-studio-inputs { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .ai-studio-admin .ai-studio-field { display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: #9ca3af; }
        .ai-studio-admin .ai-studio-field input { padding: 8px; border-radius: 10px; border: 1px solid #2c313c; background: #0f1115; color: #e5e7eb; }
        .ai-studio-admin .ai-studio-composer { display: flex; flex-direction: column; gap: 12px; }
        .ai-studio-admin .ai-studio-composer-row { position: relative; display: flex; align-items: center; gap: 12px; background: #171a21; border-radius: 14px; padding: 12px; border: 1px solid #262b36; }
        .ai-studio-admin .ai-studio-textarea { flex: 1; border-radius: 12px; border: none; background: transparent; color: #e5e7eb; padding: 8px; resize: none; }
        .ai-studio-admin .ai-studio-actions { display: flex; gap: 12px; }
        .ai-studio-admin .ai-studio-button { background: #3b82f6; color: #ffffff; border: none; padding: 10px 16px; border-radius: 12px; cursor: pointer; }
        .ai-studio-admin .ai-studio-button.secondary { background: #1f2430; color: #e5e7eb; border: 1px solid #2c313c; }
        .ai-studio-admin .ai-studio-button.icon { width: 40px; height: 40px; padding: 0; border-radius: 12px; background: #1f2430; color: #e5e7eb; border: 1px solid #2c313c; }
        .ai-studio-admin .ai-studio-icon-button { background: #1f2430; border: 1px solid #2c313c; color: #e5e7eb; border-radius: 10px; padding: 6px 10px; cursor: pointer; }
        .ai-studio-admin .ai-studio-plus-menu { position: absolute; background: #1f2430; border: 1px solid #2c313c; border-radius: 12px; padding: 8px; display: flex; flex-direction: column; gap: 6px; margin-left: 48px; margin-top: -6px; z-index: 10; }
        .ai-studio-admin .ai-studio-plus-menu button { background: #111318; color: #e5e7eb; border: 1px solid #2c313c; border-radius: 10px; padding: 6px 10px; cursor: pointer; text-align: left; }
        .ai-studio-admin .ai-studio-error { color: #f87171; font-size: 13px; }
        .ai-studio-admin .ai-studio-block-placeholder { border: 1px dashed #4b5563; padding: 24px; border-radius: 12px; text-align: center; color: #9ca3af; }
        .ai-studio-admin .ai-studio-workspace { background: #111318; border-radius: 14px; padding: 12px; border: 1px solid #262b36; display: flex; flex-direction: column; }
        .ai-studio-admin .ai-studio-workspace-empty { color: #9ca3af; font-size: 13px; text-align: center; margin-top: 24px; }
        .ai-studio-admin .ai-studio-iframe { width: 100%; height: 100%; border: none; border-radius: 12px; background: #0f1115; }
        .ai-studio-admin .ai-studio-modal { position: fixed; inset: 0; background: rgba(15, 17, 21, 0.7); display: flex; align-items: center; justify-content: center; z-index: 9999; }
        .ai-studio-admin .ai-studio-modal-card { background: #111318; border: 1px solid #262b36; border-radius: 16px; padding: 20px; width: 420px; color: #e5e7eb; display: flex; flex-direction: column; gap: 12px; }
        .ai-studio-admin .ai-studio-modal-card.large { width: 80vw; height: 80vh; }
        .ai-studio-admin .ai-studio-modal-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .ai-studio-admin .ai-studio-modal-header { display: flex; align-items: center; justify-content: space-between; }
        @media (max-width: 900px) {
            .ai-studio-admin .ai-studio-app { grid-template-columns: 1fr; }
        }
    </style>
    <div class="ai-studio-admin">
        <?php echo $content; ?>
    </div>
    <?php
}
