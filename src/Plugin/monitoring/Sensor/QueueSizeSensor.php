<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\Sensor\QueueSizeSensor.
 */

namespace Drupal\monitoring\Plugin\monitoring\Sensor;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\Sensor\ThresholdsSensorBase;
use Drupal;
use Drupal\Core\Form\FormStateInterface;

/**
 * Monitors number of items for a given core queue.
 *
 * @Sensor(
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
class QueueSizeSensor extends ThresholdsSensorBase {

  /**
   * Adds UI to select Queue for the sensor.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $queues = array_keys(Drupal::moduleHandler()->invokeAll('queue_info'));
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
}
