<?php

/**
 * @file
 * Contains \Drupal\monitoring\Sensor\Sensors\SensorValueComparisonBase
 */

namespace Drupal\monitoring\Sensor\Sensors;

use Drupal\Core\Form\FormStateInterface;
use Drupal\monitoring\Sensor\SensorConfigurable;
use Drupal\monitoring\Result\SensorResultInterface;

/**
 * Provides abstract functionality for a value comparison sensor.
 *
 * Uses "value" offset to store the expected value against which the actual
 * value will be compared to. You can prepopulate this offset with initial
 * value that will be used as the expected one on the sensor enable.
 */
abstract class SensorValueComparisonBase extends SensorConfigurable {

  /**
   * Gets the value description that will be shown in the settings form.
   *
   * @return string
   *   Value description.
   */
  abstract protected function getValueDescription();

  /**
   * Gets the actual value.
   *
   * @return mixed
   *   The actual value.
   */
  abstract protected function getActualValue();

  /**
   * Gets the actual value as text.
   *
   * @return string
   *   The expected value.
   */
  protected function getActualValueText() {
    if ($this->info->isBool()) {
      $actual_value = $this->getActualValue() ? 'TRUE' : 'FALSE';
    }
    else {
      $actual_value = $this->getActualValue();
    }

    return $actual_value;
  }

  /**
   * Gets the expected value.
   *
   * @return mixed
   *   The expected value.
   */
  protected function getExpectedValue() {
    return $this->info->getSetting('value');
  }

  /**
   * Adds expected value setting field into the sensor settings form.
   */
  public function settingsForm($form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['value'] = array(
      '#title' => 'Expected value',
      '#description' => $this->getValueDescription(),
      '#default_value' => $this->getActualValue(),
    );

    if ($this->info->isNumeric()) {
      $form['value']['#type'] = 'number';
    }
    elseif ($this->info->isBool()) {
      $form['value']['#type'] = 'checkbox';
    }
    else {
      $form['value']['#type'] = 'textfield';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    $result->setValue($this->getActualValue());
    $result->setExpectedValue($this->getExpectedValue());
  }
}
