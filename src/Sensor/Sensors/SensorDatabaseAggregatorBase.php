<?php
/**
 * @file
 * Contains \Drupal\monitoring\Sensor\Sensors\SensorSimpleDatabaseAggregator.
 */

namespace Drupal\monitoring\Sensor\Sensors;

use Drupal\Core\Form\FormStateInterface;
use Drupal\monitoring\Sensor\SensorThresholds;

/**
 * Base class for database aggregator sensors.
 *
 * Defines sensor settings:
 * - conditions: A list of conditions to apply to the query.
 *   - field: Name of the field to filter on. Configurable fields are supported
 *     using the field_name.column_name syntax.
 *   - value: The value to limit by, either an array or a scalar value.
 *   - operator: Any of the supported operators.
 * - time_interval_field: Timestamp field name
 * - time_interval_value: Number of seconds defining the period
 *
 * Adds time interval to sensor settings form.
 */
abstract class SensorDatabaseAggregatorBase extends SensorThresholds {

  /**
   * Gets conditions to be used in the select query.
   *
   * @return array
   *   List of conditions where each condition is an associative array:
   *   - field: Name of the field to filter on. Configurable fields are
   *     supported using the field_name.column_name syntax.
   *   - value: The value to limit by, either an array or a scalar value.
   *   - operator: Any of the supported operators.
   */
  protected function getConditions() {
    return $this->sensorConfig->getSetting('conditions', array());
  }

  /**
   * Gets the time filed.
   *
   * @return string
   *   Time interval field.
   */
  protected function getTimeIntervalField() {
    return $this->sensorConfig->getSetting('time_interval_field');
  }

  protected function getTimeIntervalValue() {
    return $this->sensorConfig->getTimeIntervalValue();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm($form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['time_interval_value'] = array(
      '#type' => 'select',
      '#title' => t('Aggregate time interval'),
      '#options' => $this->getTimeIntervalOptions(),
      '#description' => t('Select the time interval for which the results will be aggregated.'),
      '#default_value' => $this->sensorConfig->getTimeIntervalValue(),
    );

    return $form;
  }

  /**
   * Returns time interval options.
   *
   * @return array
   *   Array with time interval options, keyed by time interval in seconds.
   */
  protected function getTimeIntervalOptions() {
    $time_intervals = array(
      600,
      900,
      1800,
      3600,
      7200,
      10800,
      21600,
      32400,
      43200,
      64800,
      86400,
      172800,
      259200,
      604800,
      1209600,
      2419200,
    );
    $date_formatter = \Drupal::service('date.formatter');
    return array_map(array($date_formatter, 'formatInterval'), array_combine($time_intervals, $time_intervals)) + array(0 => t('No restriction'));
  }
}
