<?php
if (!defined('ABSPATH')) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
    <style>
        html, body { margin: 0; padding: 0; height: 100%; }
        body.ai-studio { background: #0f1115; color: #e5e7eb; }
        .ai-studio-shell { min-height: 100vh; display: flex; flex-direction: column; }
        .ai-studio-content { flex: 1; padding: 24px; }
        .ai-studio-app { display: grid; grid-template-columns: 280px 1fr; gap: 16px; min-height: 80vh; }
        .ai-studio-sidebar { background: #171a21; color: #e5e7eb; border-radius: 14px; padding: 18px; display: flex; flex-direction: column; gap: 16px; border: 1px solid #262b36; }
        .ai-studio-sidebar-title { font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: #9ca3af; margin-bottom: 8px; }
        .ai-studio-sidebar-section { display: flex; flex-direction: column; gap: 8px; }
        .ai-studio-sidebar-empty { font-size: 13px; color: #9ca3af; }
        .ai-studio-select { width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #2c313c; background: #1f2430; color: #e5e7eb; }
        .ai-studio-conversation-list { display: flex; flex-direction: column; gap: 8px; }
        .ai-studio-conversation { text-align: left; background: #1f2430; color: #e5e7eb; border: 1px solid #2c313c; border-radius: 12px; padding: 10px; cursor: pointer; }
        .ai-studio-conversation.active { border-color: #3b82f6; }
        .ai-studio-conversation-title { font-size: 13px; font-weight: 600; }
        .ai-studio-conversation-last { font-size: 12px; color: #9ca3af; margin-top: 4px; }
        .ai-studio-main { background: #111318; border-radius: 14px; padding: 18px; display: flex; flex-direction: column; gap: 16px; border: 1px solid #262b36; }
        .ai-studio-topbar { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border-radius: 12px; background: #171a21; border: 1px solid #262b36; color: #e5e7eb; }
        .ai-studio-topbar-label { font-size: 12px; text-transform: uppercase; color: #9ca3af; margin-right: 8px; }
        .ai-studio-topbar-value { font-weight: 600; }
        .ai-studio-topbar-right { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #9ca3af; }
        .ai-studio-status-dot { width: 8px; height: 8px; border-radius: 999px; background: #22c55e; display: inline-block; }
        .ai-studio-chat { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding: 8px; border: 1px solid #262b36; border-radius: 14px; min-height: 320px; background: #0f1115; }
        .ai-studio-message { padding: 12px; border-radius: 12px; background: #1a1f29; color: #e5e7eb; }
        .ai-studio-message-role { font-size: 12px; text-transform: uppercase; color: #9ca3af; margin-bottom: 6px; }
        .ai-studio-user { background: #1f2937; }
        .ai-studio-assistant { background: #1a1f29; }
        .ai-studio-inputs { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .ai-studio-field { display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: #9ca3af; }
        .ai-studio-field input { padding: 8px; border-radius: 10px; border: 1px solid #2c313c; background: #0f1115; color: #e5e7eb; }
        .ai-studio-composer { display: flex; flex-direction: column; gap: 12px; }
        .ai-studio-composer-row { display: flex; align-items: center; gap: 12px; background: #171a21; border-radius: 14px; padding: 12px; border: 1px solid #262b36; }
        .ai-studio-textarea { flex: 1; border-radius: 12px; border: none; background: transparent; color: #e5e7eb; padding: 8px; resize: none; }
        .ai-studio-actions { display: flex; gap: 12px; }
        .ai-studio-button { background: #3b82f6; color: #ffffff; border: none; padding: 10px 16px; border-radius: 12px; cursor: pointer; }
        .ai-studio-button.secondary { background: #1f2430; color: #e5e7eb; border: 1px solid #2c313c; }
        .ai-studio-button.icon { width: 40px; height: 40px; padding: 0; border-radius: 12px; background: #1f2430; color: #e5e7eb; border: 1px solid #2c313c; }
        .ai-studio-error { color: #f87171; font-size: 13px; }
        .ai-studio-block-placeholder { border: 1px dashed #4b5563; padding: 24px; border-radius: 12px; text-align: center; color: #9ca3af; }
        @media (max-width: 900px) {
            .ai-studio-app { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body <?php body_class('ai-studio'); ?>>
    <div class="ai-studio-shell">
        <div class="ai-studio-content">
            <?php
            while (have_posts()) {
                the_post();
                the_content();
            }
            ?>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
