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
    return $this->info->getSetting('conditions', array());
  }

  /**
   * Gets the time field.
   *
   * @return string
   *   Time interval field.
   */
  protected function getTimeIntervalField() {
    return $this->info->getTimeIntervalField();
  }

  /**
   * Gets time interval value.
   *
   * @return int
   *   Time interval value.
   */
  protected function getTimeIntervalValue() {
    return $this->info->getTimeIntervalValue();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm($form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['aggregation'] = array(
      '#type' => 'fieldset',
      '#title' => 'Time Aggregation',
    );

    $form['aggregation']['time_interval_field'] = array(
      '#type' => 'textfield',
      '#title' => t('Timestamp field'),
      '#description' => t('A UNIX timestamp in seconds since epoch.'),
      '#default_value' => $this->getTimeIntervalField(),
    );

    $form['aggregation']['time_interval_value'] = array(
      '#type' => 'select',
      '#title' => t('Interval'),
      '#options' => $this->getTimeIntervalOptions(),
      '#description' => t('Select the time interval for which the results will be aggregated.'),
      '#default_value' => $this->getTimeIntervalValue(),
      '#states' => array(
        'invisible' => array(
          ':input[name="settings[aggregation][time_interval_field]"]' => array('value' => ""),
        ),
      )
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

  /**
   * {@inheritdoc}
   */
  public function settingsFormSubmit($form, FormStateInterface $form_state) {
    parent::settingsFormSubmit($form, $form_state);

    $sensor_info = $form_state->getFormObject()->getEntity();

    // Copy time interval field & value into settings.
    $interval_field = $form_state->getValue(array(
      'settings', 'aggregation', 'time_interval_field'));
    $sensor_info->settings['time_interval_field'] = $interval_field;
    $interval_value = $form_state->getValue(array(
      'settings', 'aggregation', 'time_interval_value'));
    $sensor_info->settings['time_interval_value'] = $interval_value;
    // Remove UI structure originated settings leftover.
    unset($sensor_info->settings['aggregation']);

    return $form_state;
  }
}
