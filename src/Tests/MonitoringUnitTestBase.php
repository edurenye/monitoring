<?php
/**
 * @file
 * Contains \Drupal\monitoring\Tests\MonitoringTestBase.
 */

namespace Drupal\monitoring\Tests;

use Drupal\simpletest\KernelTestBase;

/**
 * Base class for all monitoring unit tests.
 */
abstract class MonitoringUnitTestBase extends KernelTestBase {

  public static $modules = array('monitoring', 'monitoring_test', 'field', 'system', 'user', 'views', 'text');

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('monitoring_sensor_result');
    $this->installConfig(array('monitoring', 'monitoring_test'));
  }

  /**
   * Executes a sensor and returns the result.
   *
   * @param string $sensor_name
   *   Name of the sensor to execute.
   *
   * @return \Drupal\monitoring\Result\SensorResultInterface
   *   The sensor result.
   */
  protected function runSensor($sensor_name) {
    // Make sure the sensor is enabled.
    monitoring_sensor_manager()->enableSensor($sensor_name);
    return monitoring_sensor_run($sensor_name, TRUE, TRUE);
  }

}
