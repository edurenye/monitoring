<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\Sensor\SensorPaymentTurnover.
 */

namespace Drupal\monitoring\Plugin\monitoring\Sensor;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\Sensor\DatabaseAggregatorSensorBase;

/**
 * Monitors payment turnover stats.
 *
 * A custom database query is used here instead of entity manager for
 * performance reasons.
 *
 * @Sensor(
 *   id = "payment_turnover",
 *   label = @Translation("Payment Turnover"),
 *   description = @Translation("Monitors how much money was transferred for payments with effective date."),
 *   addable = TRUE
 * )
 */
class PaymentTurnoverSensor extends DatabaseAggregatorSensorBase {

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $sensor_result) {

    // Joins payment_status and payment_line_item to filter on configured time
    // and currency code.
    $statement = db_select('payment_status', 'ps');
    $statement->join('payment_line_item', 'pli', 'pli.payment_id = ps.id');
    $statement
      ->condition('created', 'NOW() - ' . (int) $this->sensorConfig->getTimeIntervalValue(), '>')
      ->condition('currency_code', $this->sensorConfig->getValueLabel(), '=')
      ->addExpression('SUM(pli.amount_total)');

    $line_items_value = $statement->execute()->fetchField();
    $sensor_result->setValue($line_items_value);
  }

}
