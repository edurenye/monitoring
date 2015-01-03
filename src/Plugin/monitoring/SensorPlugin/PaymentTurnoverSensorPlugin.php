<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\SensorPaymentTurnover.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\SensorPlugin\DatabaseAggregatorSensorPluginBase;

/**
 * Monitors payment turnover stats.
 *
 * A custom database query is used here instead of entity manager for
 * performance reasons.
 *
 * @SensorPlugin(
 *   id = "payment_turnover",
 *   label = @Translation("Payment Turnover"),
 *   description = @Translation("Monitors how much money was transferred for payments with effective date."),
 *   provider = "payment",
 *   addable = TRUE
 * )
 */
class PaymentTurnoverSensorPlugin extends DatabaseAggregatorSensorPluginBase {

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
