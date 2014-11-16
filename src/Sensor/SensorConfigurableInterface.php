<?php
/**
 * @file
 * Monitoring sensor settings interface.
 */

namespace Drupal\monitoring\Sensor;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for a configurable sensor.
 *
 * Base interface defining implicit operations for a monitoring sensor exposing
 * custom settings.
 *
 * @todo more
 */
interface SensorConfigurableInterface {

  /**
   * Gets settings form for a specific sensor.
   *
   * @param array $form
   *   Drupal $form structure.
   * @param FormStateInterface $form_state
   *   Drupal $form_state object. Carrying the string sensor_name.
   *
   * @return array
   *   Drupal form structure.
   */
  public function settingsForm($form, FormStateInterface $form_state);

  /**
   * Form validator for a sensor settings form.
   *
   * @param array $form
   *   Drupal $form structure.
   * @param FormStateInterface $form_state
   *   Drupal $form_state object. Carrying the string sensor_name.
   */
  public function settingsFormValidate($form, FormStateInterface $form_state);

  /**
   * Form Submitter for a sensor settings form.
   *
   * @param array $form
   *   Drupal $form structure.
   * @param FormStateInterface $form_state
   *   Drupal $form_state object. Carrying the string sensor_name.
   */
  public function settingsFormSubmit($form, FormStateInterface $form_state);

}
