<?php
/**
 * @file
 * Drush support for monitoring.
 */

use Drupal\Component\Utility\Json;
use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Unicode;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\Sensor\DisabledSensorException;
use Drupal\monitoring\Sensor\NonExistingSensorException;

/**
 * Exit code for a critical sensor status.
 */
define('MONITORING_DRUSH_SENSOR_STATUS_CRITICAL', 3);
/**
 * Exit code for an unknown sensor status.
 */
define('MONITORING_DRUSH_SENSOR_STATUS_UNKNOWN', 2);
/**
 * Exit code for a warning sensor status.
 */
define('MONITORING_DRUSH_SENSOR_STATUS_WARNING', 1);
/**
 * Exit code for a proper run with OK status.
 */
define('MONITORING_DRUSH_SENSOR_STATUS_OK', 0);

/**
 * Implements hook_drush_command().
 *
 * Provides commands:
 * - monitoring-sensor-config
 * - monitoring-run
 * - monitoring-enable
 * - monitoring-disable
 * - monitoring-purge-settings
 */
function monitoring_drush_command() {
  $items = array();

  $items['monitoring-sensor-config'] = array(
    'callback' => 'monitoring_drush_sensor_config',
    'description' => 'Displays information about available sensors. If a sensor name provided as argument a detailed sensor config is provided.',
    'arguments' => array(
      'sensor_name' => 'Specific sensor name for which we want to display info.'
    ),
    'examples' => array(
      'drush monitoring-sensor-config' => 'Prints sensor config for all available sensors.',
      'drush monitoring-sensor-config node_new' => 'Prints info of the node_new sensor.',
    ),
    'drupal dependencies' => array('monitoring'),
  );

  $items['monitoring-run'] = array(
    'callback' => 'monitoring_drush_run',
    'description' => 'Runs specific sensor and provides verbose data.',
    'arguments' => array(
      'sensor_name' => 'Sensor name to invoke.',
    ),
    'options' => array(
      'verbose' => 'Display verbose information.',
      'force' => 'If the sensor execution should be forced in case cached result is available.',
      'output' => 'The output format. Currently "table" and "json" available. Defaults to "table".',
      'expand' => 'Relevant only for the json output. Currently "sensor" value supported.',
      'show-exec-time' => 'Relevant for the table output listing all results. Will expand the table with execution time info.'
    ),
    'examples' => array(
      'drush monitoring-result monitoring_git_dirty_tree' => 'Runs sensor to monitor the git status.',
      'drush monitoring-result --verbose monitoring_git_dirty_tree' => 'Runs sensor to monitor the git status and displays verbose information.',
      'drush monitoring-result --output=json --watchdog=disable' => 'Will output the sensor results in json format. The option --watchdog=disable will suppress watchdog output to console.',
    ),
    'drupal dependencies' => array('monitoring'),
  );

  $items['monitoring-enable'] = array(
    'callback' => 'monitoring_drush_enable',
    'description' => 'Enable specified monitoring sensor.',
    'arguments' => array(
      'sensor_name' => 'Sensor name to enable.',
    ),
    'required-arguments' => TRUE,
    'examples' => array(
      'drush monitoring-enable monitoring_git_dirty_tree' => 'Enables monitoring_git_dirty_tree sensor.',
    ),
    'drupal dependencies' => array('monitoring'),
  );

  $items['monitoring-disable'] = array(
    'callback' => 'monitoring_drush_disable',
    'description' => 'Disable specified monitoring sensor.',
    'arguments' => array(
      'sensor_name' => 'Sensor name to disable.',
    ),
    'required-arguments' => TRUE,
    'examples' => array(
      'drush monitoring-disable monitoring_git_dirty_tree' => 'Disables monitoring_git_dirty_tree sensor.',
    ),
    'drupal dependencies' => array('monitoring'),
  );

  $items['monitoring-purge-settings'] = array(
    'callback' => 'monitoring_drush_purge_settings',
    'description' => 'Purges settings for a specific sensor, or all sensors.',
    'arguments' => array(
      'sensor_name' => 'Sensor name for which to purge settings.',
    ),
    'examples' => array(
      'drush monitoring-purge-settings monitoring_git_dirty_tree' => 'Enables monitoring_git_dirty_tree sensor.',
    ),
    'drupal dependencies' => array('monitoring'),
  );

  return $items;
}

/**
 * Drush callback to get available sensors info.
 *
 * Shows either all sensors as a table or a specific one.
 *
 * @param string $sensor_name
 *   Sensor name for which to print info.
 */
function monitoring_drush_sensor_config($sensor_name = NULL) {
  if (empty($sensor_name)) {
    monitoring_drush_sensor_config_all();
  }
  else {
    monitoring_drush_sensor_config_single($sensor_name);
  }
}

/**
 * Provides info about available sensors.
 */
function monitoring_drush_sensor_config_all() {
  $sensors_config_all = monitoring_sensor_manager()->getAllSensorConfig();

  $rows[] = array(dt('Label'), dt('Name'), dt('Category'), dt('Enabled'));
  $rows[] = array('----', '----', '----');
  /** @var \Drupal\monitoring\Entity\SensorConfig $config */
  foreach ($sensors_config_all as $name => $sensors_config) {
    $rows[] = array(
      $config->getLabel(),
      $name,
      $config->getCategory(),
      ($config->isEnabled() ? t('Yes') : t('No')),
    );
  }

  drush_print_table($rows, TRUE);
}

/**
 * Prints detailed info about the given sensor $sensor_name.
 *
 * @param string $sensor_name
 *   Sensor name for which to print info.
 */
function monitoring_drush_sensor_config_single($sensor_name) {
  $sensor_config = NULL;
  try {
    $sensor_config = monitoring_sensor_manager()->getSensorConfigByName($sensor_name);
  }
  catch (NonExistingSensorException $e) {
    return drush_set_error('MONITORING_SENSOR_INVALID_NAME', dt('Sensor "@name" does not exist.', array('@name' => $sensor_name)));
  }

  $rows[] = array(String::format("@label (@id)", array('@label' => $sensor_config->getLabel(), '@id' => $sensor_config->id())), '====================');
  $rows[] = array(dt('Category'), $sensor_config->getCategory());
  $rows[] = array(dt('Description'), $sensor_config->getDescription());
  $rows[] = array(dt('Value info'), String::format('type: @type, label: @label, numeric: @numeric', array(
    '@type' => ($sensor_config->getValueType() ? $sensor_config->getValueType() : dt('N/A')),
    '@label' => ($sensor_config->getValueLabel() ? $sensor_config->getValueLabel() : dt('N/A')),
    '@numeric' => ($sensor_config->isNumeric() ? dt('Yes') : dt('No')),
  )));
  $rows[] = array(dt('Caching time'), \Drupal::service('date.formatter')->formatInterval($sensor_config->getCachingTime()));
  $rows[] = array(dt('Enabled'), $sensor_config->isEnabled() ? dt('Yes') : dt('No'));
  $rows[] = array(dt('Has thresholds'), $sensor_config->isDefiningThresholds() ? dt('Yes') : dt('No'));

  drush_print_table($rows);
}

/**
 * Drush callback to get sensor results.
 *
 * @param string $sensor_name
 *   Sensor name to run.
 *
 * @return int
 *   The most escalated sensor status.
 *   0 - OK
 *   1 - WARNING
 *   2 - UNKNOWN
 *   3 - CRITICAL
 */
function monitoring_drush_run($sensor_name = NULL) {
  $force_run = (bool) drush_get_option('force');
  $verbose = (bool) drush_get_option('verbose');
  $output = drush_get_option('output', 'table');
  $expand = drush_get_option('expand');
  $show_exec_time = drush_get_option('show-exec-time');

  try {
    $sensor_names = array();
    if (!empty($sensor_name)) {
      $sensor_names = array($sensor_name);
    }
    $results = monitoring_sensor_run_multiple($sensor_names, $force_run, $verbose);
  }
  catch (NonExistingSensorException $e) {
    drush_set_error('MONITORING_SENSOR_INVALID_NAME', dt('Sensor "@name" does not exist.', array('@name' => $sensor_name)));
    return MONITORING_DRUSH_SENSOR_STATUS_UNKNOWN;
  }
  catch (DisabledSensorException $e) {
    drush_set_error('MONITORING_SENSOR_DISABLED', dt('Sensor "@name" is not enabled.', array('@name' => $sensor_name)));
    return MONITORING_DRUSH_SENSOR_STATUS_UNKNOWN;
  }

  if ($output == 'table') {
    monitoring_drush_result_output_table($results, $show_exec_time);
  }
  elseif ($output == 'json') {
    monitoring_drush_result_output_json($results, $expand);
  }
  else {
    drush_set_error('MONITORING_UNKNOWN_OUTPUT', dt('Unknown output @output.', array('@output' => $output)));
    return MONITORING_DRUSH_SENSOR_STATUS_UNKNOWN;
  }

  $status = MONITORING_DRUSH_SENSOR_STATUS_OK;
  foreach ($results as $result) {
    if ($result->isCritical() && in_array($status, array(MONITORING_DRUSH_SENSOR_STATUS_UNKNOWN, MONITORING_DRUSH_SENSOR_STATUS_WARNING, MONITORING_DRUSH_SENSOR_STATUS_OK))) {
      $status = MONITORING_DRUSH_SENSOR_STATUS_CRITICAL;
    }
    elseif ($result->isUnknown() && in_array($status, array(MONITORING_DRUSH_SENSOR_STATUS_WARNING, MONITORING_DRUSH_SENSOR_STATUS_OK))) {
      $status = MONITORING_DRUSH_SENSOR_STATUS_UNKNOWN;
    }
    elseif ($result->isWarning() && $status == MONITORING_DRUSH_SENSOR_STATUS_OK) {
      $status = MONITORING_DRUSH_SENSOR_STATUS_WARNING;
    }
  }

  // Set the exit status based on the sensor status.
  return $status;
}

/**
 * Results output in JSON format.
 *
 * @param \Drupal\monitoring\Result\SensorResultInterface[] $results
 *   List of sensor result objects.
 * @param string $expand
 *   Additional data to be expanded.
 */
function monitoring_drush_result_output_json(array $results, $expand = NULL) {
  $json_output = array();
  foreach ($results as $result) {
    $sensor_name = $result->getSensorName();
    $json_output[$sensor_name] = $result->toArray();
    if ($expand == 'sensor') {
      $json_output[$sensor_name]['sensor'] = $result->getSensorConfig()->toArray();
    }
  }
  drush_print(Json::encode($json_output));
}

/**
 * Outputs human readable table with results.
 *
 * @param \Drupal\monitoring\Result\SensorResultInterface[] $results
 *   List of sensor result objects.
 * @param bool $show_exec_time
 *   If TRUE the multi results view will display also execution time.
 */
function monitoring_drush_result_output_table(array $results, $show_exec_time = FALSE) {
  if (count($results) == 1) {
    monitoring_drush_result_output_table_single(reset($results));
  }
  else {
    monitoring_drush_result_output_table_multiple($results, $show_exec_time);
  }
}

/**
 * Outputs single sensor result.
 *
 * @param \Drupal\monitoring\Result\SensorResultInterface $result
 *   Sensor result object.
 */
function monitoring_drush_result_output_table_single(SensorResultInterface $result) {
  $rows[] = array(dt('ID'), $result->getSensorConfig()->id());
  $rows[] = array(dt('Label'), $result->getSensorConfig()->getLabel());
  $rows[] = array(dt('Status'), $result->getStatusLabel());
  $rows[] = array(dt('Message'), $result->getMessage());
  $rows[] = array(dt('Execution time'), $result->getExecutionTime() . 'ms');
  $rows[] = array(dt('Result age'), \Drupal::service('date.formatter')->formatInterval(time() - $result->getTimestamp()));

  // Add the verbose output if requested.
  if ($result->getVerboseOutput()) {
    $rows[] = array(dt('Verbose output'), $result->getVerboseOutput());
  }

  drush_print_table($rows);
}

/**
 * Outputs sensor results table for multiple results.
 *
 * @param \Drupal\monitoring\Result\SensorResultInterface[] $results
 *   List of sensor result objects.
 * @param bool $show_exec_time
 *   If TRUE the multi results view will display also execution time.
 */
function monitoring_drush_result_output_table_multiple(array $results, $show_exec_time = FALSE) {
  $rows['header'] = array(dt('Sensor'), dt('Status'), dt('Message'), dt('Result age'));
  if ($show_exec_time) {
    $rows['header'][] = dt('Execution time');
  }

  foreach ($results as $result) {
    $rows[$result->getSensorId()] = array(
      String::format("@label\n(@id)", array('@label' => Unicode::truncate($result->getSensorConfig()->getLabel(), 40, TRUE, TRUE), '@id' => $result->getSensorId())),
      $result->getStatusLabel(),
      Unicode::truncate($result->getMessage(), 40, TRUE, TRUE),
      \Drupal::service('date.formatter')->formatInterval(time() - $result->getTimestamp()),
    );

    if ($show_exec_time) {
      $rows[$result->getSensorId()][] = $result->getExecutionTime() . 'ms';
    }
  }

  drush_print_table($rows);
}

/**
 * Drush callback to enable a sensor.
 *
 * @param string $sensor_name
 *   Sensor name.
 */
function monitoring_drush_enable($sensor_name) {
  $sensor_manager = monitoring_sensor_manager();
  try {
    $sensor_config = $sensor_manager->getSensorConfigByName($sensor_name);
    if (!$sensor_config->isEnabled()) {
      $sensor_manager->enableSensor($sensor_name);
      drush_log(dt('The sensor @name was enabled.', array('@name' => $sensor_config->getLabel())), 'ok');
    }
    else {
      drush_log(dt('The sensor @name is already enabled.', array('@name' => $sensor_config->getLabel())), 'warning');
    }

  }
  catch (NonExistingSensorException $e) {
    drush_set_error('MONITORING_SENSOR_INVALID_NAME', dt('Sensor "@name" does not exist.', array('@name' => $sensor_name)));
  }
}

/**
 * Drush callback to disable a sensor.
 *
 * @param string $sensor_name
 *   Sensor name.
 */
function monitoring_drush_disable($sensor_name) {
  $sensor_manager = monitoring_sensor_manager();
  try {
    $sensor_config = $sensor_manager->getSensorConfigByName($sensor_name);
    if (!$sensor_config->isEnabled()) {
      $sensor_manager->enableSensor($sensor_name);
      drush_log(dt('The sensor @name was disabled.', array('@name' => $sensor_config->getLabel())), 'ok');
    }
    else {
      drush_log(dt('The sensor @name is currently disabled.', array('@name' => $sensor_config->getLabel())), 'warning');
    }
  }
  catch (NonExistingSensorException $e) {
    drush_set_error('MONITORING_SENSOR_INVALID_NAME', dt('Sensor "@name" does not exist.', array('@name' => $sensor_name)));
  }
}

/**
 * Drush callback to purge sensor settings.
 *
 * @param string $sensor_name
 *   Sensor name for which to purge settings.
 */
function monitoring_drush_purge_settings($sensor_name = NULL) {
  if (empty($sensor_name)) {
    if (drush_confirm(dt('Do you want to purge all sensor settings?'))) {
      foreach (monitoring_sensor_manager()->getAllSensorConfig() as $sensor_name => $sensor_config) {
        \Drupal::config('monitoring.settings')->set($sensor_name, array())->save();
      }
      drush_print(dt('Purged settings for all sensors'));
    }
    return;
  }

  \Drupal::config('monitoring.settings')->set($sensor_name, array())->save();
  drush_print(dt('Purged settings for sensor @sensor_name', array('@sensor_name' => $sensor_name)));
}
