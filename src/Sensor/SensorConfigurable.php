<?php
/**
 * @file
 * Contains \Drupal\monitoring\Sensor\SensorConfigurable.
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
abstract class SensorConfigurable extends Sensor implements SensorConfigurableInterface {

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
}
