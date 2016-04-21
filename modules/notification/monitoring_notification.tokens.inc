<?php

/**
 * @file
 * Builds tokens for Monitoring notification.
 */

use Drupal\Core\Url;
use Drupal\monitoring\Entity\SensorResultEntity;

/**
 * Implements hook_token_info().
 */
function monitoring_notification_token_info() {
  // Token types.
  $tokens['types']['monitoring_sensor_result'] = [
    'name' => t('Sensor result'),
    'description' => t('Tokens for Sensor result.'),
    'needs-data' => 'monitoring_sensor_result',
  ];

  // Tokens.
  $tokens['tokens']['monitoring_sensor_result']['label'] = [
    'name' => t("Label"),
    'description' => t("Label of the Sensor result."),
  ];

  $tokens['tokens']['monitoring_sensor_result']['url'] = [
    'name' => t("URL"),
    'description' => t("The URL of the Sensor result."),
    'type' => 'url',
  ];

  return $tokens;
}

/**
 * Implements hook_tokens().
 */
function monitoring_notification_tokens($type, $tokens, array $data = [], array $options = []) {
  $replacements = [];

  if ($type == 'entity' && $data['entity_type'] == 'monitoring_sensor_result' && !empty($data['entity'])) {
    if (($monitoring_sensor_result = $data['entity']) && $monitoring_sensor_result instanceof SensorResultEntity) {
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'label':
            $replacements[$original] = $monitoring_sensor_result->label();
            break;

          case 'url':
            $url = new Url('entity.monitoring_sensor_config.details_form', ['monitoring_sensor_config' => $monitoring_sensor_result->get('sensor_name')->value]);
            $replacements[$original] = $url->toString();
            break;

        }
      }
    }
  }

  return $replacements;
}
