<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\Sensor\PaymentCountSensor.
 */

namespace Drupal\monitoring\Plugin\monitoring\Sensor;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\Sensor\DatabaseAggregatorSensorBase;

/**
 * Monitors payment count.
 *
 * @Sensor(
 *   id = "payment_count",
 *   label = @Translation("Payment Count"),
 *   description = @Translation("Monitors the number of successful transactions for payments with effective date."),
 *   addable = TRUE
 * )
 */
class PaymentCountSensor extends DatabaseAggregatorSensorBase {

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $sensor_result) {

    // Counts the payments from payment_status created within the configured
    // time interval.
    $statement = db_select('payment_status', 'ps')
      ->condition('created', REQUEST_TIME - (int) $this->sensorConfig->getTimeIntervalValue(), '>');
    $statement->addExpression('COUNT(*)');

    $sensor_result->setValue($statement->execute()->fetchField());
  }

}
