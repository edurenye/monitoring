<?php

/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\Sensor\SensorStateValue
 */

namespace Drupal\monitoring\Plugin\monitoring\Sensor;

use Drupal;
use Drupal\monitoring\Sensor\Sensors\SensorValueComparisonBase;

/**
 * Generic sensor that checks for a state value.
 *
 * @Sensor(
 *   id = "state_value",
 *   label = @Translation("State Value"),
 *   description = @Translation("Checks for a specific state value."),
 *   addable = FALSE
 * )
 */
class SensorStateValue extends SensorValueComparisonBase {

  /**
   * {@inheritdoc}
   */
  protected function getValueDescription() {
    return (t('The expected value of state %key, actual value: %actVal',
      array(
        '%key' => $this->sensorConfig->getSetting('key'),
        '%actVal' => $this->getActualValueText(),
      )));
  }

  /**
   * {@inheritdoc}
   */
  protected function getActualValue() {
    $state = $this->getState();
    $key = $this->sensorConfig->getSetting('key');
    if (empty($key)) {
      return NULL;
    }
    return $state->get($key);
  }

  /**
   * Gets state.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   *   The state.
   */
  protected function getState() {
    return $this->getService('state');
  }
}
