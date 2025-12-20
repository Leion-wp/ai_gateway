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
