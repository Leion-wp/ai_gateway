<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('_return_true')) {
    function _return_true() {
        return true;
    }
}

function ai_gateway_get_json_params($request) {
    $params = $request->get_json_params();
    return is_array($params) ? $params : [];
}
