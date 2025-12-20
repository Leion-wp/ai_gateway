<?php

if (!defined('ABSPATH')) {
    exit;
}

function ai_gateway_get_ollama_url() {
    return get_option('ai_gateway_ollama_url', 'http://localhost:11434/api/generate');
}