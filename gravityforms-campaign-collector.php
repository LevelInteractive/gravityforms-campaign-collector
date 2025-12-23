<?php
/**
 * Plugin Name:       Gravity Forms - Campaign Collector
 * Plugin URI:        https://www.level.agency
 * Description:       Extends Gravity Forms to collect common marketing metadata via hidden fields as custom entry meta.
 * Version:           1.2.0
 * Requires at least: 6.3
 * Requires PHP:      8.0
 * License:           MIT
 * Author:            Mike Hennessie, Derek Cavaliero
 * Author URI:        https://www.level.agency
 * Text Domain:       lvl:gforms-campaign-collector
 */

namespace Lvl\GravityFormsCampaignCollector;

if (! defined('WPINC'))
  die;

class CampaignCollector
{
  public static $version = '1.2.0';
  private static $handle_namespace = 'lvl:gforms-campaign-collector';

  private string $_namespace = 'lvl';
  
  public array $fields = [];
  public array $json_fields = [
    'cc_attribution_json',
    'cc_consent_json',
  ];
  public array $fields_default = [
    // Campaign Collector Core Fields
    'cc_anonymous_id' => 'Campaign Collector: Anonymous ID',
    'cc_attribution_json' => 'Campaign Collector: Attribution JSON',
    'cc_consent_json' => 'Campaign Collector: Consent JSON',

    // Standard Campaign Fields
    'utm_source' => 'Source',
    'utm_medium' => 'Medium',
    'utm_campaign' => 'Campaign',
    'utm_term' => 'Term',
    'utm_content' => 'Content',
    'utm_id' => 'Campaign ID',
    'utm_source_platform' => 'Source Platform',
    'utm_marketing_tactic' => 'Marketing Tactic',
    'utm_creative_format' => 'Creative Format',

    // Common Click/Client IDs
    
    // Google Analytics
    'ga_client_id' => 'GA4: Client ID',
    'ga_session_id' => 'GA4: Session ID',

    // Google Ads
    'gclid' => 'Google Ads: gclid',
    'gbraid' => 'Google Ads: gbraid',
    'wbraid' => 'Google Ads: wbraid',

    // Meta Ads
    '_fbc' => 'Meta Ads: _fbc',
    '_fbp' => 'Meta Ads: _fbp',

    // Microsoft Ads
    'msclkid' => 'Microsoft Ads: msclkid',

    // LinkedIn Ads
    'li_fat_id' => 'LinkedIn Ads: li_fat_id',
  ];

  private static $instance = null;
  
  public static function getInstance()
  {
    if (self::$instance === null)
      self::$instance = new self();

    return self::$instance;
  }

  public function __construct()
  {
    $this->fields = $this->fields_default;
    $this->set_fields();

    add_action('init', [$this, 'init']);
    add_action('admin_init', [$this, 'admin_init']);
  }

  public function init()
  {
    add_filter('gform_form_tag', [$this, 'add_hidden_fields'], 20, 2);    

    if (current_user_can('administrator'))
      add_action('wp_footer', [$this, 'add_frontend_notice'], 10, 2);
  }
  
  public function admin_init()
  { 
    add_filter('gform_entry_meta', [$this, 'define_entry_meta'], 10, 2);

    add_filter('gform_custom_merge_tags', [$this, 'define_merge_tags'], 10, 4);
    add_filter('gform_replace_merge_tags', [$this, 'replace_merge_tags'], 10, 7);

    add_action('gform_editor_pre_render', [$this, 'add_collection_notice_to_editor'], 10, 2);

    add_filter('gform_entry_detail_meta_boxes', [$this, 'entry_details_meta_box'], 10, 3);

    add_filter('gform_noconflict_styles', [$this, 'allow_list_styles'], 10, 1);
	  add_filter('gform_noconflict_scripts', [$this, 'allow_list_scripts'], 10, 1);
	  
    add_action('admin_enqueue_scripts', [$this, 'load_admin_css_js'], 9999, 1);
  }

  public function set_fields(?array $form = null)
  {
    $base_filter =  "{$this->_namespace}:gform_campaign_collector/set_fields";

    $this->fields = !empty($form) ? 
      apply_filters("$base_filter/form/{$form['id']}", $this->fields, $form)
      :
      apply_filters($base_filter, $this->fields);

    if (empty($this->fields) || ! is_array($this->fields))
      $this->fields = $this->fields_default;

    $this->json_fields = apply_filters("{$this->_namespace}:gform_campaign_collector/json_fields", $this->json_fields);
  }

  public function meta_key(string $key): string
  {
    // Stores the meta key as lvl:{$key} to avoid collisions with other meta keys.
    return implode(':', [$this->_namespace, $key]);
  }

  public function define_entry_meta(array $entry_meta, int $form): array
  {
    foreach ($this->fields as $key => $label) {
      $entry_meta[$this->meta_key($key)] = [
        'label' => $label,
        'is_numeric' => false,
        'is_default' => false,
        'is_required' => false,
        'update_entry_meta_callback' => [$this, 'update_entry_meta'],
        'context' => 'form',
      ];
    }

    return $entry_meta;
  }

  public function update_entry_meta($key, $lead, $form) 
  {
    $key = explode(':', $key)[1];

    if (! array_key_exists($key, $this->fields))
      return;

    $value = $_POST[$key] ?? '';

    $value = in_array($key, $this->json_fields) ? $this->validate_json_value($value) : $this->sanitize_text_value($value);

    return $value;
  }

  public function add_hidden_fields(string $form_tag, array $form): string
  {
    $this->set_fields($form);

    $hidden_fields = [
      '<div class="gform_campaign_collector_fields" style="display:none;">',
    ];

    foreach ($this->fields as $key => $label) {
      $value = isset($_GET[$key]) ? ' value="' . $this->sanitize_text_value($_GET[$key]) . '"' : '';
      $hidden_fields[] = '<input type="hidden" name="' . $key . '"' . $value . ' />';
    }

    $hidden_fields[] = '</div>';

    return $form_tag . implode("", $hidden_fields);
  }

  public function define_merge_tags(array $merge_tags, int $form_id, array $fields, string|int $element_id): array
  {
    foreach ($this->fields as $key => $label) {
      $merge_tags[] = [
        'label' => $label,
        'tag' => '{' . $this->meta_key($key). '}',
      ];
    }

    return $merge_tags;
  }

  public function replace_merge_tags(string $text, array|bool $form, array|bool $entry, bool $url_encode, bool $esc_html, bool $nl2br, string $format)
  {
    foreach ($this->fields as $key => $label) {
      $merge_tag = "{{$this->meta_key($key)}}";

      if (strpos($text, $merge_tag) !== false)
        $text = str_replace($merge_tag, gform_get_meta($entry['id'], $this->meta_key($key)), $text);
    }

    return $text;
  }

  public function entry_details_meta_box(array $meta_boxes, array $entry, array $form): array
  {
    $meta_boxes[] = [
      'title' => $this->branding() . 'Campaign Collector: Entry Meta',
      'context' => 'normal',
      'priority' => 'high',
      'callback' => [$this, 'entry_details_meta_fields'],
    ];

    return $meta_boxes;
  }

  public function entry_details_meta_fields(array $args)
  {
    $form  = $args['form'];
    $entry = $args['entry'];

    $output = [
      '<div style="margin: -12px -12px 0;">',
      '<table cellspacing="0" class="entry-details-table" style="border: 0; border-radius: 0; box-shadow: none; table-layout: fixed;">',
      '<tbody>',
    ];

    foreach ($this->fields as $key => $label) {

      $meta_key = $this->meta_key($key);
      
      $value = gform_get_meta($entry['id'], $meta_key);

      $type = 'text';

      if (in_array($key, $this->json_fields)) {
        $type = 'json';
        $value = $this->custom_json_pretty_print(json_decode($value, true));
        $value = '<pre><code class="language-json">' . $value . '</code></pre>'; // <pre style="margin: 0; white-space: pre; max-width: 100%; overflow-x: auto;">
      }
      
      if (empty($value))
        continue;

      $output[] = implode("\n", [
        '<tr>',
        '<td colspan="2" class="entry-view-field-name"><div style="display: flex; align-items: center; justify-content: space-between;"><span>' . $label . '</span><code class="gform-campaign-collector-badge">' . $key . '</code></td>',
        '</tr>',
        '<tr>',
        '<td colspan="2" class="entry-view-field-value entry-view-field-value--' . $type . '" style="font-family: \'Fira Code\', monospace; word-wrap: break-word; white-space: normal;">' . $value . '</td>',
        '</tr>',
      ]);
    }

    $output[] = '</tbody>';
    $output[] = '</table>';
    $output[] = '</div>';

    echo implode("\n", $output);
  }

  private function validate_json_value(string $maybe_json): string
  {
    if (strpos($maybe_json, '\"') !== false)
      $maybe_json = stripslashes($maybe_json);
    
    return json_decode($maybe_json) !== null ? $maybe_json : '';
  }

  public function allow_list_styles(array $handles)
  {
    foreach ([
      'fonts',
      'styles',
	  //'prism-css',
      'prism-theme',
    ] as $style) {
      $handles[] = self::$handle_namespace . '_' . $style;
    }

    return $handles;
  }
	
  public function allow_list_scripts(array $handles)
  {
	  foreach ([
      'prism-core',
      'prism-autoloader',
    ] as $script) {
      $handles[] = self::$handle_namespace . '_' . $script;
    }

    return $handles;
  }

  public function load_admin_css_js(string $hook)
  {  
    if (! \RGForms::is_gravity_page())
      return;

    wp_enqueue_style(self::$handle_namespace . '_fonts', 'https://fonts.googleapis.com/css2?family=Fira+Code:wght@300..700&display=swap', [], '1.0.0');
    wp_enqueue_style(self::$handle_namespace . '_styles', plugin_dir_url(__FILE__) . 'admin/styles.css', [], '1.0.0');

    // wp_enqueue_style(self::$handle_namespace . '_prism-css', 'https://cdn.jsdelivr.net/npm/prismjs@latest/themes/prism.min.css', [], '1.0.0');
    wp_enqueue_style(self::$handle_namespace . '_prism-theme', 'https://cdn.jsdelivr.net/npm/prism-themes@latest/themes/prism-atom-dark.css', [], '1.0.0');
    wp_enqueue_script(self::$handle_namespace . '_prism-core', 'https://cdn.jsdelivr.net/npm/prismjs@latest/components/prism-core.min.js', [], '1.0.0');
    wp_enqueue_script(self::$handle_namespace . '_prism-autoloader', 'https://cdn.jsdelivr.net/npm/prismjs@latest/plugins/autoloader/prism-autoloader.min.js', [], '1.0.0');
  }

  public function branding(): string
  {
    return <<<HTML
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
      <rect fill="#000000" width="512" height="512"/>
      <path fill="#ffffff" d="m90.32,451c-9.18,0-16.65-7.47-16.65-16.65s7.47-16.65,16.65-16.65h331.35c9.18,0,16.65,7.47,16.65,16.65s-7.47,16.65-16.65,16.65H90.32Zm0-59.45c-9.18,0-16.65-7.47-16.65-16.65s7.47-16.65,16.65-16.65h331.35c9.18,0,16.65,7.47,16.65,16.65s-7.47,16.65-16.65,16.65H90.32Zm0-59.45c-9.18,0-16.65-7.47-16.65-16.65s7.47-16.65,16.65-16.65h175.35c9.18,0,16.65,7.47,16.65,16.65s-7.47,16.65-16.65,16.65H90.32Zm0-59.45c-9.18,0-16.65-7.47-16.65-16.65s7.47-16.65,16.65-16.65h175.35c9.18,0,16.65,7.47,16.65,16.65s-7.47,16.65-16.65,16.65H90.32Zm0-59.45c-9.18,0-16.65-7.47-16.65-16.65s7.47-16.65,16.65-16.65h175.35c9.18,0,16.65,7.47,16.65,16.65s-7.47,16.65-16.65,16.65H90.32Zm0-59.45c-9.18,0-16.65-7.47-16.65-16.65s7.47-16.65,16.65-16.65h175.35c9.18,0,16.65,7.47,16.65,16.65s-7.47,16.65-16.65,16.65H90.32Zm0-59.45c-9.18,0-16.65-7.47-16.65-16.65s7.47-16.65,16.65-16.65h175.35c9.18,0,16.65,7.47,16.65,16.65s-7.47,16.65-16.65,16.65H90.32Z"/>
    </svg>
    HTML;
  }
  
  public function add_collection_notice_to_editor($form)
  {
    // Note - I don't like this - its a weird pattern to set this but it works for a v1.
    // Its necessary to make sure the "lvl:gform_campaign_collector/set_fields/form/X" filters run before the output is rendered.
    $this->set_fields($form);

    $fields_as_table_rows = implode("\n", array_map(function($key, $label) {
      return '<tr><td>' . $label . '</td><td><code class="gform-campaign-collector-badge">' . $key . '</code></td></tr>';
    }, array_keys($this->fields), $this->fields));

    ?>
    <script>
      window.addEventListener("load", (event) => {

        const formFields = document.getElementById('gform_fields');

        const notice = document.createElement('details');
        notice.className = "gform-theme__disable-reset";
        notice.setAttribute('data-addon', 'lvl:gforms-campaign-collector');

        notice.innerHTML = `
        <summary>
          <div class="icons">
            <span class="icon">
              <i class="gform-icon gform-icon--drag-indicator"></i>
            </span>
            <span class="icon">
              <?php echo $this->branding() ?>
            </span>
          </div>
          <span class="badge">Campaign Collector: Entry Meta</span>
        </summary>
        <div class="content">
          <div class="alert">
            All forms are automatically configured to collect the following data as custom entry meta. These fields can be modified/extended via the <code class="gform-campaign-collector-badge">lvl:gform_campaign_collector/set_fields</code> filter hook in your theme (if needed).
          </div>
          <table>
            <thead>
              <tr>
                <th>Meta Field Name</th>
                <th>Input Name</th>
              </tr>
            </thead>
            <tbody>
            <?php echo $fields_as_table_rows; ?>
            </tbody>
          </table>
        </div>
        `;

        formFields.parentElement.insertBefore(notice, formFields);

      });
    </script>
    <?php
  }

  private function sanitize_text_value(string $unsafe_text)
  {
    $safer_text = sanitize_text_field($unsafe_text);
    return preg_replace('/[^a-zA-Z0-9_\-\%\.\?\@\#\&\+\~\$\!\:\;\,\=\/\|\[\]\{\}\(\)\ ]/u', '', $safer_text);
  }

  private function custom_json_pretty_print($data)
  {
    $json = json_encode($data, JSON_PRETTY_PRINT);
    $json = preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $json);
    
    return $json;
  }

  public function add_frontend_notice()
  {
    echo <<<HTML
    <style>
    .campaign-collector-frontend-notice{
      display: flex;
      flex-direction: column;
      margin: 0;
      gap: 0.25rem;
      padding: 0.75rem 1rem;
      border: 1px solid #86d5f4;
      background-color: #ecf8fd;
      box-shadow: 0 1px 4px rgba(18,25,97,.0779552);
      color: #4b4f63;
      border-radius: 3px;
      font-size: 0.875rem;
      line-height: 1.25rem;
      font-weight: 400;
      width: 24rem;
      position: fixed;
      bottom: 1rem;
      left: 1rem;
      z-index: 1000;
    }
    .campaign-collector-frontend-notice a{
      color: #118BBB;
    }
    .campaign-collector-frontend-notice > *:last-child{
      margin-bottom: 0;
    }
    </style>
    <script>
    window.addEventListener("load", (event) => {

      const insertNotice = () => {
        const notice = document.createElement('div');
        notice.className = "campaign-collector-frontend-notice";

        notice.innerHTML = `
          <strong>CampaignCollector.js not detected!</strong>
          <div>Make sure you have the <a href="https://github.com/LevelInteractive/campaign-collector" target="_blank">CampaignCollector.js library</a> loaded globally on the website.</div>
          <small>This message is only shown to logged in administrators.</small>
        `;

        document.body.appendChild(notice);
      };

      let count = 0;
      const limit = 5;
      const check = setInterval(() => {

        count++;

        let limitReached = count >= limit;

        if (window.CampaignCollector !== undefined || limitReached)
          clearInterval(check);

        if (limitReached)
          insertNotice();

      }, 1000);

    });
    </script>
    HTML;
  }
}

add_action('plugins_loaded', function() {
  CampaignCollector::getInstance();
});