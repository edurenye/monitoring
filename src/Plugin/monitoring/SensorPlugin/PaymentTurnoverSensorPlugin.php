<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\SensorPaymentTurnover.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\payment\Entity\Payment;

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
class PaymentTurnoverSensorPlugin extends EntityAggregatorSensorPlugin {

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $sensor_result) {
    // @todo This will not perform for large number of payments.
    // @todo Use a condition for the currency when available again.
    $ids = $this->getEntityQuery()->execute();

    $payments = $this->entityManager
      ->getStorage($this->getEntityType())
      ->loadMultiple($ids);

    $turnover = 0;
    foreach ($payments as $payment) {
      foreach ($payment->getLineItems() as $line_item) {
        // @todo Add a form for this setting.
        if ($line_item->getCurrencyCode() == $this->sensorConfig->getSetting('currency_code')) {
          $turnover += $line_item->getAmount();
        }
      }
    }
    $sensor_result->setValue($turnover);
  }

}
