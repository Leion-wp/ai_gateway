<?php

if (!defined('ABSPATH')) {
    exit;
}

class AIGateway_Updater {
    private $plugin_file;
    private $plugin_slug;
    private $version;
    private $repo_owner;
    private $repo_name;
    private $cache_key;
    private $cache_ttl;

    public function __construct($plugin_file, $version, $repo_owner, $repo_name) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = $version;
        $this->repo_owner = $repo_owner;
        $this->repo_name = $repo_name;
        $this->cache_key = 'ai_gateway_update_info';
        $this->cache_ttl = 6 * HOUR_IN_SECONDS;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_updates']);
        add_filter('plugins_api', [$this, 'plugins_api'], 10, 3);
    }

    public function check_updates($transient) {
        if (empty($transient->checked) || empty($transient->checked[$this->plugin_slug])) {
            return $transient;
        }

        $release = $this->get_release();
        if (!$release || empty($release['version']) || empty($release['download_url'])) {
            return $transient;
        }

        if (version_compare($this->version, $release['version'], '>=')) {
            return $transient;
        }

        $transient->response[$this->plugin_slug] = (object) [
            'slug' => $this->plugin_slug,
            'plugin' => $this->plugin_slug,
            'new_version' => $release['version'],
            'url' => $release['html_url'],
            'package' => $release['download_url'],
        ];

        return $transient;
    }

    public function plugins_api($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (empty($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }

        $release = $this->get_release();
        if (!$release) {
            return $result;
        }

        return (object) [
            'name' => 'AI Gateway',
            'slug' => dirname($this->plugin_slug),
            'version' => $release['version'],
            'author' => 'Eion',
            'homepage' => $release['html_url'],
            'download_link' => $release['download_url'],
            'sections' => [
                'description' => 'Local-first AI agents for Gutenberg with native tools.',
                'changelog' => 'See GitHub Releases for details.',
            ],
        ];
    }

    private function get_release() {
        $cached = get_transient($this->cache_key);
        if ($cached) {
            return $cached;
        }

        $url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->repo_owner, $this->repo_name);
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'AI-Gateway-Updater',
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return null;
        }

        $version = isset($body['tag_name']) ? ltrim($body['tag_name'], 'v') : '';
        $download_url = '';
        if (!empty($body['assets']) && is_array($body['assets'])) {
            foreach ($body['assets'] as $asset) {
                if (!empty($asset['name']) && strpos($asset['name'], '_no_src.zip') !== false) {
                    $download_url = $asset['browser_download_url'] ?? '';
                    break;
                }
            }
        }

        if (!$version || !$download_url) {
            return null;
        }

        $data = [
            'version' => $version,
            'download_url' => $download_url,
            'html_url' => $body['html_url'] ?? '',
        ];

        set_transient($this->cache_key, $data, $this->cache_ttl);
        return $data;
    }
}