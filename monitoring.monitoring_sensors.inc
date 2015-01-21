<?php
/**
 * @file
 * Define default sensors for core and contrib modules.
 *
 * Default sensors provide sensors for core and contrib modules with
 * monitoring_MODULE_monitoring_sensor_info()
 *
 * The default integration for MODULE is omitted if a module implements
 * hook_monitoring_sensor_info() on its own.
 *
 * @see hook_monitoring_sensor_info()
 */

use Drupal\Component\Utility\String;

/**
 * Implements monitoring_MODULE_monitoring_sensor_info().
 *
 * Module: elysia_cron
 */
function monitoring_elysia_cron_monitoring_sensor_info() {
  $info = array();

  $result = db_select('elysia_cron', 'e')->fields('e', array('name'))->execute()->fetchAll();

  foreach ($result as $row) {
    // Remove the colon from the name as it breaks munin graphs.
    $name = str_replace(':', '', $row->name);
    $info['elysia_cron_' . $name . '_last_execution'] = array(
      'label' => String::format('Elysia @name execution', array('@name' => $row->name)),
      'description' => String::format('Last execution duration for elysia @name cron', array('@name' => $row->name)),
      'plugin_id' => 'elysia_cron',
      'value_type' => 'time_interval',
      'settings' => array(
        'category' => 'Elysia Cron',
        'enabled' => FALSE,
        'name' => $row->name,
        'metric' => 'last_execution_time',
      ),
    );
    $info['elysia_cron_' . $name . '_last_run'] = array(
      'label' => String::format('Elysia @name run', array('@name' => $row->name)),
      'description' => String::format('Time since last elysia @name run', array('@name' => $row->name)),
      'plugin_id' => 'elysia_cron',
      'value_type' => 'time_interval',
      'settings' => array(
        'category' => 'Elysia Cron',
        'enabled' => FALSE,
        'name' => $row->name,
        'metric' => 'last_run',
      ),
    );
  }

  return $info;
}

/**
 * Implements monitoring_MODULE_monitoring_sensor_info().
 *
 * Module: commerce_order
 */
function monitoring_commerce_order_monitoring_sensor_info() {
  $info = array();

  // Expose a sensor for each order status, disabled by default.
  foreach (commerce_order_statuses() as $status_name => $status_info) {
    $info['commerce_order_status_' . $status_name] = array(
      'label' => String::format('Orders in status @name', array('@name' => $status_info['title'])),
      'plugin_id' => 'database_aggregator',
      'value_label' => 'Orders',
      'settings' => array(
        'enabled' => FALSE,
        'category' => 'Commerce',
        'entity_type' => 'commerce_order',
        'conditions' => array(
          array('field' => 'status', 'value' => $status_name),
        ),
        'time_interval_field' => 'created',
        'time_interval_value' => 60 * 60 * 24,
        'caching_time' => 3600,
      ),
    );
  }

  // Expose a turnover sensor for each currency.
  $currencies = commerce_currencies(TRUE);
  foreach ($currencies as $currency) {
    $info['commerce_order_turnover_' . strtolower($currency['code'])] = array(
      'label' => String::format('Turnover in @currency', array('@currency' => $currency['code'])),
      'description' => String::format('Turnover from completed orders in @currency', array('@currency' => $currency['code'])),
      'plugin_id' => 'commerce_turnover',
      'value_label' => $currency['code'],
      'value_type' => 'commerce_currency',
      'settings' => array(
        'category' => 'Commerce',
        'table' => 'commerce_order',
        'conditions' => array(
          'status' => array(
            'field' => 'status',
            'value' => array('pending'),
            'operator' => 'IN',
          ),
        ),
        'time_interval_field' => 'created',
        'time_interval_value' => 60 * 60 * 24,
        'caching_time' => 3600,
        'currency_code' => $currency['code'],
      ),
    );
  }
  // Provide total turnover sensor if we have more than one enabled currency
  // that sums up all currencies and reports in the default currency.
  if (count($currencies) > 1) {
    $info['commerce_order_turnover_total'] = array(
      'label' => 'Total turnover',
      'description' => 'Total turnover from completed orders in all currencies',
      'plugin_id' => 'commerce_turnover',
      'value_label' => $currencies[commerce_default_currency()]['code'],
      'value_type' => 'commerce_currency',
      'settings' => array(
        'category' => 'Commerce',
        'table' => 'commerce_order',
        'conditions' => array(
          'status' => array(
            'field' => 'status',
            'value' => array('pending'),
            'operator' => 'IN',
          ),
        ),
        'time_interval_field' => 'created',
        'caching_time' => 3600,
        'time_interval_value' => 60 * 60 * 24,
      ),
    );
  }

  return $info;
}
