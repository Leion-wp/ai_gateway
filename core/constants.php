<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AI_GATEWAY_VERSION')) {
    define('AI_GATEWAY_VERSION', '2.1.0');
}

if (!defined('AI_GATEWAY_PLUGIN_DIR')) {
    define('AI_GATEWAY_PLUGIN_DIR', plugin_dir_path(AI_GATEWAY_PLUGIN_FILE));
}

if (!defined('AI_GATEWAY_PLUGIN_URL')) {
    define('AI_GATEWAY_PLUGIN_URL', plugin_dir_url(AI_GATEWAY_PLUGIN_FILE));
}

if (!defined('AI_GATEWAY_POST_TYPE')) {
    define('AI_GATEWAY_POST_TYPE', 'ai_agent');
}

if (!defined('AI_GATEWAY_STUDIO_POST_TYPE')) {
    define('AI_GATEWAY_STUDIO_POST_TYPE', 'ai_studio');
}

if (!defined('AI_GATEWAY_PROJECT_POST_TYPE')) {
    define('AI_GATEWAY_PROJECT_POST_TYPE', 'ai_project');
}

if (!defined('AI_GATEWAY_CONVERSATION_POST_TYPE')) {
    define('AI_GATEWAY_CONVERSATION_POST_TYPE', 'ai_conversation');
}
