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
    $this->installSchema('payment', array('payment_line_item', 'payment_status'));
  }

  /**
   * Tests for payment count and turnover sensors.
   */
  public function testPaymentSensors() {

    $payment_method = Payment::methodManager()->createInstance('payment_test');
    $payment = Generate::createPayment(4, $payment_method);
    $payment->save();

    $sensor_config = SensorConfig::create(array(
      'id' => 'payment_count',
      'plugin_id' => 'payment_count',
      'value_label' => 'transactions',
      'caching_time' => 3600,
      'settings' => array(
        'time_interval_value' => 86400
      )
    ));
    $sensor_config->save();
    $result = $this->runSensor('payment_count');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), '1 transactions in 1 day');

    $sensor_config = SensorConfig::create(array(
      'id' => 'payment_turnover',
      'plugin_id' => 'payment_turnover',
      'caching_time' => 3600,
      'value_label' => 'JPY',
      'settings' => array(
        'time_interval_value' => 86400
      )
    ));
    $sensor_config->save();
    $result = $this->runSensor('payment_turnover');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), '11 jpy in 1 day');
  }

}
