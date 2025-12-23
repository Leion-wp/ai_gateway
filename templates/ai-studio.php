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
        body.ai-studio { background: #f6f7f7; }
        .ai-studio-shell { min-height: 100vh; display: flex; flex-direction: column; }
        .ai-studio-content { flex: 1; padding: 24px; }
        .ai-studio-app { display: grid; grid-template-columns: 260px 1fr; gap: 16px; min-height: 80vh; }
        .ai-studio-sidebar { background: #111827; color: #f9fafb; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 16px; }
        .ai-studio-sidebar-title { font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: #9ca3af; margin-bottom: 8px; }
        .ai-studio-sidebar-section { display: flex; flex-direction: column; gap: 8px; }
        .ai-studio-sidebar-empty { font-size: 13px; color: #9ca3af; }
        .ai-studio-select { width: 100%; padding: 8px; border-radius: 8px; border: 1px solid #374151; background: #1f2937; color: #f9fafb; }
        .ai-studio-conversation-list { display: flex; flex-direction: column; gap: 8px; }
        .ai-studio-conversation { text-align: left; background: #1f2937; color: #f9fafb; border: 1px solid #374151; border-radius: 10px; padding: 10px; cursor: pointer; }
        .ai-studio-conversation.active { border-color: #38bdf8; }
        .ai-studio-conversation-title { font-size: 13px; font-weight: 600; }
        .ai-studio-conversation-last { font-size: 12px; color: #9ca3af; margin-top: 4px; }
        .ai-studio-main { background: #ffffff; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 16px; }
        .ai-studio-chat { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding: 8px; border: 1px solid #e5e7eb; border-radius: 12px; min-height: 320px; }
        .ai-studio-message { padding: 12px; border-radius: 12px; background: #f3f4f6; }
        .ai-studio-message-role { font-size: 12px; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }
        .ai-studio-user { background: #e0f2fe; }
        .ai-studio-assistant { background: #f3f4f6; }
        .ai-studio-inputs { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .ai-studio-field { display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: #374151; }
        .ai-studio-field input { padding: 8px; border-radius: 8px; border: 1px solid #d1d5db; }
        .ai-studio-composer { display: flex; flex-direction: column; gap: 12px; }
        .ai-studio-textarea { width: 100%; border-radius: 12px; border: 1px solid #d1d5db; padding: 12px; }
        .ai-studio-actions { display: flex; gap: 12px; }
        .ai-studio-button { background: #111827; color: #ffffff; border: none; padding: 10px 16px; border-radius: 10px; cursor: pointer; }
        .ai-studio-button.secondary { background: #e5e7eb; color: #111827; }
        .ai-studio-error { color: #b91c1c; font-size: 13px; }
        .ai-studio-block-placeholder { border: 1px dashed #9ca3af; padding: 24px; border-radius: 12px; text-align: center; color: #6b7280; }
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
