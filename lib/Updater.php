<?php

namespace Lvl\GravityForms\CampaignCollector;

if (! defined('WPINC'))
  die;

class Updater
{
  private $plugin_file;
  private $plugin_slug;
  private $version;
  private $update_uri;
  private $remote_data = null;

  public function __construct($plugin_file, $version) 
  {
    $this->plugin_file = $plugin_file;
    $this->plugin_slug = basename($plugin_file, '.php');
    $this->update_uri = "https://wordpress.level-cdn.com/api/plugins/{$this->plugin_slug}/versions/latest";
    $this->version = $version;

    add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdate']);
    add_filter('plugins_api', [$this, 'pluginInfo'], 20, 3);
  }

  public function checkForUpdate($transient) 
  {
    if (empty($transient->checked))
      return $transient;

    $plugin_path = plugin_basename($this->plugin_file);
        
    if (!isset($transient->checked[$plugin_path]))
      return $transient;

    $remote_data = $this->getRemoteData();
      
    if (!$remote_data)
      return $transient;

    $remote_version = $remote_data['wordpress']['new_version'] ?? false;

    if (!$remote_version || !version_compare($this->version, $remote_version, '<'))
      return $transient;

    $transient->response[$plugin_path] = (object) [
      'slug' => $this->plugin_slug,
      'new_version' => $remote_version,
      'package' => $remote_data['wordpress']['package'] ?? '',
      'url' => $remote_data['wordpress']['details_url'] ?? '',
    ];

    return $transient;
  }

  public function pluginInfo($result, $action, $args) 
  {
    if ($action !== 'plugin_information')
      return $result;

    if (!isset($args->slug) || $args->slug !== $this->plugin_slug)
      return $result;

    $remote_data = $this->getRemoteData();

    if (!$remote_data)
      return $result;

    $version_data = $remote_data['version'] ?? [];
    $wp_data = $remote_data['wordpress'] ?? [];

    return (object) [
      'name' => $this->plugin_slug,
      'slug' => $this->plugin_slug,
      'version' => $wp_data['new_version'] ?? $this->version,
      'author' => $version_data['author']['login'] ?? '',
      'author_profile' => $version_data['author']['html_url'] ?? '',
      'homepage' => $version_data['html_url'] ?? '',
      'download_link' => $wp_data['package'] ?? '',
      'trunk' => $wp_data['package'] ?? '',
      'last_updated' => $version_data['published_at'] ?? '',
      'sections' => [
        // 'description' => $wp_data['release_notes'] ?? '',
        'changelog' => $this->formatChangelog($version_data),
      ],
    ];
  }

  private function getRemoteData() 
  {
    if ($this->remote_data !== null)
      return $this->remote_data;

    $cache_key = 'lvl_updater_' . $this->plugin_slug;
    $cached = get_transient($cache_key);

    if ($cached !== false) {
      $this->remote_data = $cached;
      return $this->remote_data;
    }

    $response = wp_remote_get($this->update_uri, [
      'timeout' => 10,
      'headers' => [
        'Accept' => 'application/json',
      ],
    ]);

    if (is_wp_error($response)) {
      error_log('Updater Error: ' . $response->get_error_message());
      return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    
    if ($status_code !== 200) {
      error_log("Updater Error: HTTP {$status_code} from {$this->update_uri}");
      return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log('Updater Error: Invalid JSON response');
      return false;
    }

    set_transient($cache_key, $data, HOUR_IN_SECONDS * 6);
    
    $this->remote_data = $data;
    return $this->remote_data;
  }

  private function formatChangelog($version_data) 
  {
    if (empty($version_data['body']))
      return '';

    $changelog = "<h4>{$version_data['tag_name']}</h4>";
    $changelog .= wpautop($version_data['body']);

    return $changelog;
  }
}
