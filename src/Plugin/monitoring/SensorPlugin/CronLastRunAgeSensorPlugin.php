<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\CronLastRunAgeSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\monitoring\SensorPlugin\ThresholdsSensorPluginBase;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal;

/**
 * Monitors the last cron run time.
 *
 * @SensorPlugin(
 *   id = "cron_last_run_time",
 *   label = @Translation("Cron Last Run Age"),
 *   description = @Translation("Monitors the last cron run time."),
 *   addable = FALSE
 * )
 *
 * Based on the drupal core system state cron_last.
 */
class CronLastRunAgeSensorPlugin extends ThresholdsSensorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    $last_cron_run_before = REQUEST_TIME - \Drupal::state()->get('system.cron_last');
    $result->setValue($last_cron_run_before);
    $result->addStatusMessage('@time ago', array('@time' => \Drupal::service('date.formatter')->formatInterval($last_cron_run_before)));
  }
}