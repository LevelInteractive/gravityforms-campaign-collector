# Gravity Forms - Campaign Collector

## Filters

### `lvl:gform_campaign_collector/set_fields`

```php
// Runs for all forms
add_filter('lvl:gform_campaign_collector/set_fields', function(array $fields){

  $fields['my_field_key'] = 'My Field Label';

  return $fields;

}, 10);
```

### `lvl:gform_campaign_collector/set_fields/form/$form_id`

```php
// Only runs for Form ID 2
add_filter('lvl:gform_campaign_collector/set_fields/form/2', function(array $fields, array $form){

  $fields['my_special_field_key'] = 'My Special Field Label';

  return $fields;

}, 10, 2);
```
