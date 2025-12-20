<?php
/*
Plugin Name: AI Gateway
Description: Connecte WordPress a Ollama et MCP via Gutenberg.
Version: 2.1.0
Author: Eion
Text Domain: ai-gateway
*/

if (!defined('ABSPATH')) {
    exit;
}

define('AI_GATEWAY_PLUGIN_FILE', __FILE__);

require_once __DIR__ . '/core/constants.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/ollama.php';
require_once __DIR__ . '/core/agents.php';

require_once __DIR__ . '/editor/sidebar.php';
require_once __DIR__ . '/editor/enqueue.php';

require_once __DIR__ . '/admin/menu.php';
require_once __DIR__ . '/admin/pages-settings.php';
require_once __DIR__ . '/admin/pages-agents.php';
require_once __DIR__ . '/admin/actions.php';
require_once __DIR__ . '/admin/pages-plugins.php';
require_once __DIR__ . '/admin/actions-plugins.php';

require_once __DIR__ . '/rest/handlers-run.php';
require_once __DIR__ . '/rest/handlers-publish.php';
require_once __DIR__ . '/rest/handlers-agents.php';
require_once __DIR__ . '/rest/handlers-media.php';
require_once __DIR__ . '/rest/handlers-plugins.php';
require_once __DIR__ . '/rest/routes.php';
