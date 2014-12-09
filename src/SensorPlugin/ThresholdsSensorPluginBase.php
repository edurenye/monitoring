<?php
/**
 * @file
 * Contains \Drupal\monitoring\SensorPlugin\ThresholdsSensorPluginBase.
 */

namespace Drupal\monitoring\SensorPlugin;

use Drupal\Core\Form\FormStateInterface;

/**
 * Abstract class with configuration form for Sensor plugins with thresholds.
 *
 * Sensors may provide thresholds that apply by default.
 * Threshold values are validated for sequence.
 */
abstract class ThresholdsSensorPluginBase extends SensorPluginBase implements ThresholdsSensorPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['thresholds'] = array(
      '#type' => 'fieldset',
      '#title' => t('Sensor thresholds'),
      '#description' => t('Here you can set limit values that switch the sensor to a given status.'),
      '#prefix' => '<div id="monitoring-sensor-thresholds">',
      '#suffix' => '</div>',
    );

    $type = $form_state->getValue(array('settings', 'thresholds', 'type'));

    if (empty($type)) {
      $type = $this->sensorConfig->getThresholdsType();
    }

    $form['thresholds']['type'] = array(
      '#type' => 'select',
      '#title' => t('Threshold type'),
      '#options' => array(
        'none' => t('- None -'),
        'exceeds' => t('Exceeds'),
        'falls' => t('Falls'),
        'inner_interval' => t('Inner interval'),
        'outer_interval' => t('Outer interval'),
      ),
      '#default_value' => $type,
      '#ajax' => array(
        'callback' => 'monitoring_sensor_thresholds_ajax',
        'wrapper' => 'monitoring-sensor-thresholds',
      ),
    );

    switch ($type) {
      case 'exceeds':
        $form['thresholds']['#description'] = t('The sensor will be set to the corresponding status if the value exceeds the limits.');
        $form['thresholds']['warning'] = array(
          '#type' => 'number',
          '#title' => t('Warning'),
          '#default_value' => $this->sensorConfig->getThresholdValue('warning'),
        );
        $form['thresholds']['critical'] = array(
          '#type' => 'number',
          '#title' => t('Critical'),
          '#default_value' => $this->sensorConfig->getThresholdValue('critical'),
        );
        break;

      case 'falls':
        $form['thresholds']['#description'] = t('The sensor will be set to the corresponding status if the value falls below the limits.');
        $form['thresholds']['warning'] = array(
          '#type' => 'number',
          '#title' => t('Warning'),
          '#default_value' => $this->sensorConfig->getThresholdValue('warning'),
        );
        $form['thresholds']['critical'] = array(
          '#type' => 'number',
          '#title' => t('Critical'),
          '#default_value' => $this->sensorConfig->getThresholdValue('critical'),
        );
        break;

      case 'inner_interval':
        $form['thresholds']['#description'] = t('The sensor will be set to the corresponding status if the value is within the limits.');
        $form['thresholds']['warning_low'] = array(
          '#type' => 'number',
          '#title' => t('Warning low'),
          '#default_value' => $this->sensorConfig->getThresholdValue('warning_low'),
        );
        $form['thresholds']['warning_high'] = array(
          '#type' => 'number',
          '#title' => t('Warning high'),
          '#default_value' => $this->sensorConfig->getThresholdValue('warning_high'),
        );
        $form['thresholds']['critical_low'] = array(
          '#type' => 'number',
          '#title' => t('Critical low'),
          '#default_value' => $this->sensorConfig->getThresholdValue('critical_low'),
        );
        $form['thresholds']['critical_high'] = array(
          '#type' => 'number',
          '#title' => t('Critical high'),
          '#default_value' => $this->sensorConfig->getThresholdValue('critical_high'),
        );
        break;

      case 'outer_interval':
        $form['thresholds']['#description'] = t('The sensor will be set to the corresponding status if the value is outside of the limits.');
        $form['thresholds']['warning_low'] = array(
          '#type' => 'number',
          '#title' => t('Warning low'),
          '#default_value' => $this->sensorConfig->getThresholdValue('warning_low'),
        );
        $form['thresholds']['warning_high'] = array(
          '#type' => 'number',
          '#title' => t('Warning high'),
          '#default_value' => $this->sensorConfig->getThresholdValue('warning_high'),
        );
        $form['thresholds']['critical_low'] = array(
          '#type' => 'number',
          '#title' => t('Critical low'),
          '#default_value' => $this->sensorConfig->getThresholdValue('critical_low'),
        );
        $form['thresholds']['critical_high'] = array(
          '#type' => 'number',
          '#title' => t('Critical high'),
          '#default_value' => $this->sensorConfig->getThresholdValue('critical_high'),
        );
        break;
    }

    return $form;
  }

  /**
   * Sets a form error for the given threshold key.
   *
   * @param string $threshold_key
   *   Key of the threshold value form element.
   * @param FormStateInterface $form_state
   *   Drupal form state object.
   * @param string $message
   *   The validation message.
   */
  protected function setFormError($threshold_key, FormStateInterface $form_state, $message) {
    $form_state->setErrorByName($this->sensorConfig->id() . '][thresholds][' . $threshold_key, $message);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue(array('settings', 'thresholds'));
    $type = $values['type'];

    switch ($type) {
      case 'exceeds':
        if (!empty($values['warning']) && !empty($values['critical']) && $values['warning'] >= $values['critical']) {
          $this->setFormError('warning', $form_state, t('Warning must be lower than critical or empty.'));
        }
        break;

      case 'falls':
        if (!empty($values['warning']) && !empty($values['critical']) && $values['warning'] <= $values['critical']) {
          $this->setFormError('warning', $form_state, t('Warning must be higher than critical or empty.'));
        }
        break;

      case 'inner_interval':
        if (empty($values['warning_low']) && !empty($values['warning_high']) || !empty($values['warning_low']) && empty($values['warning_high'])) {
          $this->setFormError('warning_low', $form_state, t('Either both warning values must be provided or none.'));
        }
        elseif (empty($values['critical_low']) && !empty($values['critical_high']) || !empty($values['critical_low']) && empty($values['critical_high'])) {
          $this->setFormError('critical_low', $form_state, t('Either both critical values must be provided or none.'));
        }
        elseif (!empty($values['warning_low']) && !empty($values['warning_high']) && $values['warning_low'] >= $values['warning_high']) {
          $this->setFormError('warning_low', $form_state, t('Warning low must be lower than warning high or empty.'));
        }
        elseif (!empty($values['critical_low']) && !empty($values['critical_high']) && $values['critical_low'] >= $values['critical_high']) {
          $this->setFormError('warning_low', $form_state, t('Critical low must be lower than critical high or empty.'));
        }
        elseif (!empty($values['warning_low']) && !empty($values['critical_low']) && $values['warning_low'] >= $values['critical_low']) {
          $this->setFormError('warning_low', $form_state, t('Warning low must be lower than critical low or empty.'));
        }
        elseif (!empty($values['warning_high']) && !empty($values['critical_high']) && $values['warning_high'] <= $values['critical_high']) {
          $this->setFormError('warning_high', $form_state, t('Warning high must be higher than critical high or empty.'));
        }
        break;

      case 'outer_interval':
        if (empty($values['warning_low']) && !empty($values['warning_high']) || !empty($values['warning_low']) && empty($values['warning_high'])) {
          $this->setFormError('warning_low', $form_state, t('Either both warning values must be provided or none.'));
        }
        elseif (empty($values['critical_low']) && !empty($values['critical_high']) || !empty($values['critical_low']) && empty($values['critical_high'])) {
          $this->setFormError('critical_low', $form_state, t('Either both critical values must be provided or none.'));
        }
        elseif (!empty($values['warning_low']) && !empty($values['warning_high']) && $values['warning_low'] >= $values['warning_high']) {
          $this->setFormError('warning_low', $form_state, t('Warning low must be lower than warning high or empty.'));
        }
        elseif (!empty($values['critical_low']) && !empty($values['critical_high']) && $values['critical_low'] >= $values['critical_high']) {
          $this->setFormError('warning_low', $form_state, t('Critical low must be lower than critical high or empty.'));
        }
        elseif (!empty($values['warning_low']) && !empty($values['critical_low']) && $values['warning_low'] <= $values['critical_low']) {
          $this->setFormError('warning_low', $form_state, t('Warning low must be higher than critical low or empty.'));
        }
        elseif (!empty($values['warning_high']) && !empty($values['critical_high']) && $values['warning_high'] >= $values['critical_high']) {
          $this->setFormError('warning_high', $form_state, t('Warning high must be lower than critical high or empty.'));
        }
        break;
    }
  }
}
