<?php
/**
 * @file
 * Contains \MonitoringApiTest.
 */

namespace Drupal\monitoring\Tests;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\Sensor\DisabledSensorException;
use Drupal\monitoring\Sensor\NonExistingSensorException;
use Drupal\monitoring\SensorRunner;
use Drupal\monitoring\Entity\SensorInfo;

/**
 * Tests for Monitoring API.
 */
class MonitoringApiTest extends MonitoringUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('dblog');

  public static function getInfo() {
    return array(
      'name' => 'Monitoring API',
      'description' => 'Tests the monitoring API',
      'group' => 'Monitoring',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('dblog', array('watchdog'));
  }

  /**
   * Test the base class if info is set and passed correctly.
   */
  public function testAPI() {

    // == Test sensor info. == //
    // @todo - complete the sensor info tests in followup.
    $sensor_info_data = array(
      'label' => 'Test sensor info',
      'description' => 'To test correct sensor info hook implementation precedence.',
      'settings' => array(),
    );
    monitoring_sensor_manager()->resetCache();
    $test_sensorInfo = SensorInfo::load('test_sensor_info');
    $sensor_info = monitoring_sensor_manager()->getSensorInfoByName('test_sensor_info');

    $this->assertEqual($sensor_info->getLabel(), $sensor_info_data['label']);
    $this->assertEqual($sensor_info->getDescription(), $sensor_info_data['description']);
    // @todo - add tests for compulsory sensor info attributes.

    // Test all defaults.
    // Flag numeric should default to TRUE.
    $this->assertEqual($sensor_info->isNumeric(), TRUE);
    // @todo - add tests for default values of attributes.

    // @todo - override remaining attributes.
    $sensor_info_data['numeric'] = FALSE;
    $test_sensorInfo->numeric = FALSE;
    // Define custom value label and NO value type. In this setup the sensor
    // defined value label must be used.
    $sensor_info_data['value_label'] = 'Test label';
    $test_sensorInfo->value_label = 'Test label';
    $test_sensorInfo->save();
    monitoring_sensor_manager()->resetCache();
    $sensor_info = monitoring_sensor_manager()->getSensorInfoByName('test_sensor_info');
    // Test all custom defined.
    // Flag numeric must be false.
    $this->assertEqual($sensor_info->isNumeric(), FALSE);
    $this->assertEqual($sensor_info->getValueLabel(), $sensor_info_data['value_label']);
    // @todo - add tests for overridden values of attributes.

    // Test value label provided by the monitoring_value_types().
    // Set the value type to one defined by the monitoring_value_types().
    $sensor_info_data['value_type'] = 'time_interval';
    $test_sensorInfo->value_type = 'time_interval';
    $test_sensorInfo->value_label = '';
    $test_sensorInfo->save();
    unset($sensor_info_data['value_label']);
    monitoring_sensor_manager()->resetCache();
    $sensor_info = monitoring_sensor_manager()->getSensorInfoByName('test_sensor_info');
    $value_types = monitoring_value_types();
    $this->assertEqual($sensor_info->getValueLabel(), $value_types['time_interval']['label']);

    // == Test basic sensor infrastructure - value, status and message. == //

    $test_sensor_result_data = array(
      'sensor_value' => 3,
      'sensor_status' => SensorResultInterface::STATUS_OK,
      'sensor_message' => 'All OK',
      'execution_time' => 1,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = $this->runSensor('test_sensor');

    $this->assertTrue($result->getExecutionTime() > 0);
    $this->assertEqual($result->getStatus(), $test_sensor_result_data['sensor_status']);
    $this->assertEqual($result->getMessage(), 'Value 3, ' . $test_sensor_result_data['sensor_message']);
    $this->assertEqual($result->getValue(), $test_sensor_result_data['sensor_value']);

    // == Test sensor result cache == //

    // Test cached result
    $result_cached = monitoring_sensor_run('test_sensor');
    $this->assertTrue($result_cached->isCached());
    $this->assertEqual($result_cached->getTimestamp(), REQUEST_TIME);
    $this->assertEqual($result_cached->getStatus(), $test_sensor_result_data['sensor_status']);
    $this->assertEqual($result_cached->getMessage(), 'Value 3, ' . $test_sensor_result_data['sensor_message']);
    $this->assertEqual($result_cached->getValue(), $test_sensor_result_data['sensor_value']);

    // Call a setter method to invalidate cache and reset values.
    $result_cached->setValue(5);
    $this->assertFalse($result_cached->isCached());

    // == Non-existing sensor error handling == //

    // Trying to fetch information for a non-existing sensor or trying to
    // execute such a sensor must throw an exception.
    try {
      monitoring_sensor_manager()->getSensorInfoByName('non_existing_sensor');
      $this->fail('Expected exception for non-existing sensor not thrown.');
    } catch (NonExistingSensorException $e) {
      $this->pass('Expected exception for non-existing sensor thrown.');
    }

    try {
      monitoring_sensor_run('non_existing_sensor');
      $this->fail('Expected exception for non-existing sensor not thrown.');
    } catch (NonExistingSensorException $e) {
      $this->pass('Expected exception for non-existing sensor thrown.');
    }

    // == Test disabled sensor. == //

    // Disable a sensor.
    monitoring_sensor_manager()->disableSensor('test_sensor');

    // Running a disabled sensor must throw an exception.
    try {
      monitoring_sensor_run('test_sensor');
      $this->fail('Expected exception for disabled sensor not thrown.');
    } catch (DisabledSensorException $e) {
      $this->pass('Expected exception for disabled sensor thrown.');
    }

    // Enable the sensor again.
    monitoring_sensor_manager()->enableSensor('test_sensor');
    $result = monitoring_sensor_run('test_sensor');
    $this->assertTrue($result instanceof SensorResultInterface);

    // == Test settings. == //

    // == inner_interval gives error statuses.

    // Test for OK values.
    $test_sensor_result_data = array(
      'sensor_value' => 11,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_inner', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_OK);
    $this->assertEqual($result->getMessage(), 'Value 11');

    $test_sensor_result_data = array(
      'sensor_value' => 0,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_inner', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_OK);
    $this->assertEqual($result->getMessage(), 'Value 0');

    // Test for warning values.
    $test_sensor_result_data = array(
      'sensor_value' => 7,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_inner', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_WARNING);
    $this->assertEqual($result->getMessage(), t('Value 7, violating the interval @expected', array('@expected' => '1 - 9')));

    $test_sensor_result_data = array(
      'sensor_value' => 2,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_inner', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_WARNING);
    $this->assertEqual($result->getMessage(), t('Value 2, violating the interval @expected', array('@expected' => '1 - 9')));

    // Test for critical values.
    $test_sensor_result_data = array(
      'sensor_value' => 5,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_inner', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_CRITICAL);
    $this->assertEqual($result->getMessage(), t('Value 5, violating the interval @expected', array('@expected' => '4 - 6')));

    $test_sensor_result_data = array(
      'sensor_value' => 5,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_inner', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_CRITICAL);
    $this->assertEqual($result->getMessage(), t('Value 5, violating the interval @expected', array('@expected' => '4 - 6')));

    // == outer_intervals give error statuses.

    // Test for ok values.
    $test_sensor_result_data = array(
      'sensor_value' => 75,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_outer', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_OK);
    $this->assertEqual($result->getMessage(), 'Value 75');

    $test_sensor_result_data = array(
      'sensor_value' => 71,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_outer', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_OK);
    $this->assertEqual($result->getMessage(), 'Value 71');

    // Test for warning values.
    $test_sensor_result_data = array(
      'sensor_value' => 69,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_outer', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_WARNING);
    $this->assertEqual($result->getMessage(), t('Value 69, outside the allowed interval @expected', array('@expected' => '70 - 80')));

    $test_sensor_result_data = array(
      'sensor_value' => 65,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_outer', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_WARNING);
    $this->assertEqual($result->getMessage(), t('Value 65, outside the allowed interval @expected', array('@expected' => '70 - 80')));

    // Test for critical values.
    $test_sensor_result_data = array(
      'sensor_value' => 55,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_outer', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_CRITICAL);
    $this->assertEqual($result->getMessage(), t('Value 55, outside the allowed interval @expected', array('@expected' => '60 - 90')));

    $test_sensor_result_data = array(
      'sensor_value' => 130,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_outer', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_CRITICAL);
    $this->assertEqual($result->getMessage(), t('Value 130, outside the allowed interval @expected', array('@expected' => '60 - 90')));

    // == Exceeds interval gives error statuses.

    $test_sensor_result_data = array(
      'sensor_value' => 4,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_exceeds', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_OK);
    $this->assertEqual($result->getMessage(), 'Value 4');

    $test_sensor_result_data = array(
      'sensor_value' => 6,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_exceeds', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_WARNING);
    $this->assertEqual($result->getMessage(), t('Value 6, exceeds @expected', array('@expected' => '5')));

    $test_sensor_result_data = array(
      'sensor_value' => 14,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_exceeds', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_CRITICAL);
    $this->assertEqual($result->getMessage(), t('Value 14, exceeds @expected', array('@expected' => '10')));

    // == Falls interval gives error statuses.

    $test_sensor_result_data = array(
      'sensor_value' => 12,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_falls', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_OK);
    $this->assertEqual($result->getMessage(), 'Value 12');

    $test_sensor_result_data = array(
      'sensor_value' => 9,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_falls', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_WARNING);
    $this->assertEqual($result->getMessage(), t('Value 9, falls below @expected', array('@expected' => '10')));

    $test_sensor_result_data = array(
      'sensor_value' => 3,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = monitoring_sensor_run('test_sensor_falls', TRUE);
    $this->assertEqual($result->getStatus(), SensorResultInterface::STATUS_CRITICAL);
    $this->assertEqual($result->getMessage(), t('Value 3, falls below @expected', array('@expected' => '5')));

    // Test the case when sensor value is not set.
    $test_sensor_result_data = array(
      'sensor_value' => NULL,
      'sensor_status' => SensorResultInterface::STATUS_CRITICAL,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $result = $this->runSensor('test_sensor');
    $this->assertNull($result->getValue());
  }

  /**
   * Test logging with different settings.
   */
  public function testLogging() {

    // First perform tests with the logging strategy in default mode - that is
    // "Log only on request or on status change".

    $test_sensor_result_data = array(
      'sensor_value' => 1,
      'sensor_message' => 'test message',
      'sensor_status' => SensorResultInterface::STATUS_OK,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $sensor = SensorInfo::load('test_sensor');
    $sensor->settings['result_logging'] = TRUE;
    $sensor->save();
    $this->runSensor('test_sensor');

    $logs = $this->loadSensorLog('test_sensor');
    $this->assertEqual(count($logs), 1);
    $log = array_shift($logs);
    $this->assertEqual($log->sensor_name->value, 'test_sensor');
    $this->assertEqual($log->sensor_status->value, SensorResultInterface::STATUS_OK);
    $this->assertEqual($log->sensor_value->value, 1);
    $this->assertEqual($log->sensor_message->value, 'Value 1, test message');

    // Set log_calls sensor settings to false - that should prevent logging.
    $sensor->settings['result_logging'] = FALSE;
    $sensor->save();
    debug(\Drupal::config('monitoring.settings')->get('test_sensor'));
    /** @var SensorRunner $runner */
    $runner = \Drupal::service('monitoring.sensor_runner');
    $runner->runSensors(array(monitoring_sensor_manager()->getSensorInfoByName('test_sensor')));
    //$this->runSensor('test_sensor');
    $logs = $this->loadSensorLog('test_sensor');
    $this->assertEqual(count($logs), 1);

    // Now change the status - that should result in the call being logged.
    $test_sensor_result_data = array(
      'sensor_status' => SensorResultInterface::STATUS_WARNING,
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);
    $this->runSensor('test_sensor');
    $logs = $this->loadSensorLog('test_sensor');
    $this->assertEqual(count($logs), 2);
    $log = array_pop($logs);
    $this->assertEqual($log->sensor_status->value, SensorResultInterface::STATUS_WARNING);

    // Set the logging strategy to "Log all events".
    \Drupal::config('monitoring.settings')->set('sensor_call_logging', 'all')->save();
    // Running the sensor with 'result_logging' settings FALSE must record the call.
    $sensor->settings['result_logging'] = FALSE;
    $sensor->save();
    $this->container->set('monitoring.sensor_runner', NULL);
    $this->runSensor('test_sensor');
    $logs = $this->loadSensorLog('test_sensor');
    $this->assertEqual(count($logs), 3);

    // Set the logging strategy to "No logging".
    \Drupal::config('monitoring.settings')->set('sensor_call_logging', 'none')->save();
    // Despite log_calls TRUE we should not log any call.
    $sensor->settings['result_logging'] = TRUE;
    $sensor->save();
    $this->container->set('monitoring.sensor_runner', NULL);
    $logs = $this->loadSensorLog('test_sensor');
    $this->runSensor('test_sensor');
    $this->assertEqual(count($logs), 3);

  }

  /**
   * Load sensor log data for a given sensor.
   *
   * @param $sensor_name
   *   The sensor name.
   *
   * @return array
   *   All log records of given sensor.
   */
  protected function loadSensorLog($sensor_name) {
    $result = \Drupal::entityQuery('monitoring_sensor_result')
      ->condition('sensor_name', $sensor_name)
      ->execute();
    return entity_load_multiple('monitoring_sensor_result', $result);
  }
}
