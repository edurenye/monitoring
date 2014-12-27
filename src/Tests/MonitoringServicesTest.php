<?php
/**
 * @file
 * Contains \Drupal\monitoring\Tests\MonitoringServicesTest.
 */

namespace Drupal\monitoring\Tests;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\monitoring\Entity\SensorConfig;
use Drupal\rest\Tests\RESTTestBase;

/**
 * Tests for cron sensor.
 */
class MonitoringServicesTest extends RESTTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('dblog', 'hal', 'rest', 'monitoring');

  /**
   * User account created.
   *
   * @var AccountInterface
   */
  protected $servicesAccount;

  public static function getInfo() {
    return array(
      'name' => 'Monitoring services',
      'description' => 'Monitoring services tests.',
      'group' => 'Monitoring',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Enable REST API for monitoring resources.
    $config = \Drupal::config('rest.settings');
    $settings = array(
      'monitoring-sensor' => array(
        'GET' => array(
          'supported_formats' => array($this->defaultFormat),
          'supported_auth' => $this->defaultAuth,
        ),
      ),
      'monitoring-sensor-result' => array(
        'GET' => array(
          'supported_formats' => array($this->defaultFormat),
          'supported_auth' => $this->defaultAuth,
        ),
      ),
    );
    $config->set('resources', $settings);
    $config->save();
    $this->rebuildCache();

    $this->servicesAccount = $this->drupalCreateUser(array('restful get monitoring-sensor', 'restful get monitoring-sensor-result'));
  }

  /**
   * Test sensor config API calls.
   */
  public function testSensorConfig() {
    $this->drupalLogin($this->servicesAccount);

    $response_data = $this->doRequest('monitoring-sensor');
    $this->assertResponse(200);

    foreach (monitoring_sensor_manager()->getAllSensorConfig() as $sensor_name => $sensor_config) {
      $this->assertEqual($response_data[$sensor_name]['sensor'], $sensor_config->id());
      $this->assertEqual($response_data[$sensor_name]['label'], $sensor_config->getLabel());
      $this->assertEqual($response_data[$sensor_name]['category'], $sensor_config->getCategory());
      $this->assertEqual($response_data[$sensor_name]['description'], $sensor_config->getDescription());
      $this->assertEqual($response_data[$sensor_name]['numeric'], $sensor_config->isNumeric());
      $this->assertEqual($response_data[$sensor_name]['value_label'], $sensor_config->getValueLabel());
      $this->assertEqual($response_data[$sensor_name]['caching_time'], $sensor_config->getCachingTime());
      $this->assertEqual($response_data[$sensor_name]['time_interval'], $sensor_config->getTimeIntervalValue());
      $this->assertEqual($response_data[$sensor_name]['enabled'], $sensor_config->isEnabled());
      $this->assertEqual($response_data[$sensor_name]['uri'], Url::fromUri('base://monitoring-sensor/' . $sensor_config->id(), array('absolute' => TRUE))->toString());

      if ($sensor_config->isDefiningThresholds()) {
        $this->assertEqual($response_data[$sensor_name]['thresholds'], $sensor_config->getSetting('thresholds'));
      }
    }

    $sensor_name = 'sensor_that_does_not_exist';
    $this->doRequest('monitoring-sensor/' . $sensor_name);
    $this->assertResponse(404);

    $sensor_name = 'dblog_event_severity_error';
    $response_data = $this->doRequest('monitoring-sensor/' . $sensor_name);
    $this->assertResponse(200);
    $sensor_config = SensorConfig::load($sensor_name);
    $this->assertEqual($response_data['sensor'], $sensor_config->id());
    $this->assertEqual($response_data['label'], $sensor_config->getLabel());
    $this->assertEqual($response_data['category'], $sensor_config->getCategory());
    $this->assertEqual($response_data['description'], $sensor_config->getDescription());
    $this->assertEqual($response_data['numeric'], $sensor_config->isNumeric());
    $this->assertEqual($response_data['value_label'], $sensor_config->getValueLabel());
    $this->assertEqual($response_data['caching_time'], $sensor_config->getCachingTime());
    $this->assertEqual($response_data['time_interval'], $sensor_config->getTimeIntervalValue());
    $this->assertEqual($response_data['enabled'], $sensor_config->isEnabled());
    $this->assertEqual($response_data['uri'], Url::fromUri('base://monitoring-sensor/' . $sensor_config->id(), array('absolute' => TRUE))->toString());

    if ($sensor_config->isDefiningThresholds()) {
      $this->assertEqual($response_data['thresholds'], $sensor_config->getSetting('thresholds'));
    }
  }

  /**
   * Test sensor result API calls.
   */
  public function testSensorResult() {
    $this->drupalLogin($this->servicesAccount);

    // Test request for sensor results with expanded sensor config.
    $response_data = $this->doRequest('monitoring-sensor-result', array('expand' => 'sensor'));
    $this->assertResponse(200);
    foreach (monitoring_sensor_manager()->getEnabledSensorConfig() as $sensor_name => $sensor_config) {
      $this->assertTrue(isset($response_data[$sensor_name]['sensor']));
      $this->assertSensorResult($response_data[$sensor_name], $sensor_config);
    }

    // Try a request without expanding the sensor config and check that it is not
    // present.
    $response_data = $this->doRequest('monitoring-sensor-result');
    $this->assertResponse(200);
    $sensor_result = reset($response_data);
    $this->assertTrue(!isset($sensor_result['sensor_info']));

    // Make sure the response contains expected count of results.
    $this->assertEqual(count($response_data), count(monitoring_sensor_manager()->getEnabledSensorConfig()));

    // Test non existing sensor.
    $sensor_name = 'sensor_that_does_not_exist';
    $this->doRequest('monitoring-sensor-result/' . $sensor_name);
    $this->assertResponse(404);

    // Test disabled sensor - note that monitoring_git_dirty_tree is disabled
    // by default.
    $sensor_name = 'monitoring_git_dirty_tree';
    $this->doRequest('monitoring-sensor-result/' . $sensor_name);
    $this->assertResponse(404);

    $sensor_name = 'dblog_event_severity_error';
    $response_data = $this->doRequest('monitoring-sensor-result/' . $sensor_name, array('expand' => 'sensor'));
    $this->assertResponse(200);
    // The response must contain the sensor.
    $this->assertTrue(isset($response_data['sensor']));
    $this->assertSensorResult($response_data, SensorConfig::load($sensor_name));

    // Try a request without expanding the sensor config and check that it is not
    // present.
    $response_data = $this->doRequest('monitoring-sensor-result/' . $sensor_name);
    $this->assertResponse(200);
    $this->assertTrue(!isset($response_data['sensor']));
  }

  /**
   * Do sensor result assertions.
   *
   * @param array $response_result
   *   Result received via response.
   * @param \Drupal\monitoring\Entity\SensorConfig $sensor_config
   *   Sensor config for which we have the result.
   */
  protected function assertSensorResult($response_result, SensorConfig $sensor_config) {
    $this->assertEqual($response_result['sensor_name'], $sensor_config->id());
    // Test the uri - the hardcoded endpoint is defined in the
    // monitoring_test.default_services.inc.
    $this->assertEqual($response_result['uri'], Url::fromUri('base://monitoring-sensor-result/' . $sensor_config->id(), array('absolute' => TRUE))->toString());

    // If the result is cached test also for the result values. In case of
    // result which is not cached we might not get the same values.
    if ($sensor_config->getCachingTime()) {
      // Cannot use $this->runSensor() as the cache needs to remain.
      $result = monitoring_sensor_run($sensor_config->id());
      $this->assertEqual($response_result['status'], $result->getStatus());
      $this->assertEqual($response_result['value'], $result->getValue());
      $this->assertEqual($response_result['expected_value'], $result->getExpectedValue());
      $this->assertEqual($response_result['numeric_value'], $result->toNumber());
      $this->assertEqual($response_result['message'], $result->getMessage());
      $this->assertEqual($response_result['timestamp'], $result->getTimestamp());
      $this->assertEqual($response_result['execution_time'], $result->getExecutionTime());
    }

    if (isset($response_result['sensor_info'])) {
      $this->assertEqual($response_result['sensor_info'], $sensor_config->toArray());
    }
  }

  /**
   * Do the request.
   *
   * @param string $action
   *   Action to perform.
   * @param array $query
   *   Path query key - value pairs.
   *
   * @return array
   *   Decoded json object.
   */
  protected function doRequest($action, $query = array()) {
    $url = Url::fromUri("base://$action", array('absolute' => TRUE, 'query' => $query))->toString();
    $result = $this->httpRequest($url, 'GET', NULL, $this->defaultMimeType);
    return Json::decode($result);
  }

}
