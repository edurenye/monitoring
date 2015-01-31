<?php
/**
 * @file
 * Contains \Drupal\monitoring\Tests\MonitoringPaymentTest.
 */

namespace Drupal\monitoring\Tests;

use Drupal\monitoring\Entity\SensorConfig;
use Drupal\payment\Tests\Generate;
use Drupal\payment\Payment;

/**
 * Tests for the payment sensor in monitoring.
 */
class MonitoringPaymentTest extends MonitoringUnitTestBase {

  public static $modules = array('payment', 'currency');

  public static function getInfo() {
    return array(
      'name' => 'Monitoring Payment',
      'description' => 'Monitoring Payment sensors tests.',
      'group' => 'Monitoring',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('payment');
  }

  /**
   * Tests for payment count and turnover sensors.
   */
  public function testPaymentSensors() {

    $payment_method = Payment::methodManager()->createInstance('payment_test');
    $payment = Generate::createPayment(4, $payment_method);
    $payment->save();

    // Create total payment count sensor.
    $sensor_config = SensorConfig::create(array(
      'id' => 'payment_count',
      'plugin_id' => 'entity_aggregator',
      'value_label' => 'transactions',
      'value_type' => 'number',
      'caching_time' => 3600,
      'settings' => array(
        'entity_type' => 'payment',
        'time_interval_value' => 86400,
        'time_interval_field' => 'created',
      )
    ));
    $sensor_config->save();
    $result = $this->runSensor('payment_count');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), '1 transactions in 1 day');

    // Create turnover sensor for JPY.
    $sensor_config = SensorConfig::create(array(
      'id' => 'payment_turnover',
      'plugin_id' => 'payment_turnover',
      'caching_time' => 3600,
      'value_label' => 'Yen',
      'value_type' => 'number',
      'settings' => array(
        'currency_code' => 'JPY',
        'entity_type' => 'payment',
        'time_interval_value' => 86400,
        'time_interval_field' => 'created',
      )
    ));
    $sensor_config->save();
    $result = $this->runSensor('payment_turnover');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), '5.5 yen in 1 day');
  }

}
