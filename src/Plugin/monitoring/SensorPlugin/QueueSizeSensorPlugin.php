<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\QueueSizeSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\monitoring\SensorPlugin\SensorPluginBase;

/**
 * Monitors number of items for a given core queue.
 *
 * @SensorPlugin(
 *   id = "queue_size",
 *   label = @Translation("Queue Size"),
 *   description = @Translation("Monitors number of items for a given core queue."),
 *   addable = TRUE
 * )
 *
 * Every instance represents a single queue.
 * Once all queue items are processed, the value should be 0.
 *
 * @see \DrupalQueue
 */
class QueueSizeSensorPlugin extends SensorPluginBase {

  /**
   * {@inheritdoc}
   */
  protected $configurableValueType = FALSE;

  /**
   * Adds UI to select Queue for the sensor.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $queues = array_keys(\Drupal::moduleHandler()->invokeAll('queue_info'));
    $form['queue'] = array(
      '#type' => 'select',
      '#options' => $queues,
      '#default_value' => $this->sensorConfig->getSetting('queue'),
      '#required' => TRUE,
      '#title' => t('Queues'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    $result->setValue(\Drupal::queue($this->sensorConfig->getSetting('queue'))->numberOfItems());
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultConfiguration() {
    $default_config = array(
      'value_type' => 'number',
    );
    return $default_config;
  }

}
