## Gravity Forms - Campaign Collector

### Default Fields

```php
$fields_default = [
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
  'dclid' => 'Google Ads: dclid',

  // Meta Ads
  '_fbc' => 'Meta Ads: _fbc',
  '_fbp' => 'Meta Ads: _fbp',

  // Microsoft Ads
  'msclkid' => 'Microsoft Ads: msclkid',

  // LinkedIn Ads
  'li_fat_id' => 'LinkedIn Ads: li_fat_id',

  // TikTok Ads
  'ttclid' => 'TikTok Ads: ttclid',
];
```

### Filters

#### `lvl:gravityforms/campaign_collector/set_fields`

```php
// Runs for all forms
add_filter('lvl:gravityforms/campaign_collector/set_fields', function(array $fields){

  $fields['my_field_key'] = 'My Field Label';

  return $fields;

}, 10);
```

#### `lvl:gravityforms/campaign_collector/set_fields/form/$form_id`

```php
// Only runs for Form ID 2
add_filter('lvl:gravityforms/campaign_collector/set_fields/form/2', function(array $fields, array $form){

  $fields['my_special_field_key'] = 'My Special Field Label';

  return $fields;

}, 10, 2);
```

#### `lvl:gravityforms/campaign_collector/field_value/$key`

```php
add_filter('lvl:gravityforms/campaign_collector/field_value/$key', function(string $value, array $form){

  /** 
   * $value (string) 
   * $form (array) - arugment gives you access to the context of the form.
   */
  $value = 'banana';

  return $value;

}, 10, 2);
```