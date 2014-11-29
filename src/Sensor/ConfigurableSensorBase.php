<?php
/**
 * @file
 * Contains \Drupal\monitoring\Sensor\ConfigurableSensorBase.
 */

namespace Drupal\monitoring\Sensor;

use Drupal\Core\Form\FormStateInterface;

/**
 * Abstract configurable sensor class.
 *
 * Sensor extension providing generic functionality for custom
 * sensor settings.
 *
 * Custom sensor settings need to be implemented in an extending class.
 */
abstract class ConfigurableSensorBase extends SensorBase implements ConfigurableSensorInterface {

  /**
   * {@inheritdoc}
   */
  public function settingsForm($form, FormStateInterface $form_state) {

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormValidate($form, FormStateInterface $form_state) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormSubmit($form, FormStateInterface $form_state) {
    // Do nothing.
  }
}
