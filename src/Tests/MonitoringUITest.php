<?php
/**
 * @file
 * Contains \MonitoringUITest.
 */

namespace Drupal\monitoring\Tests;

use Drupal\Component\Utility\String;
use Drupal\monitoring\SensorRunner;

/**
 * Tests for the Monitoring UI.
 */
class MonitoringUITest extends MonitoringTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('dblog', 'node', 'views');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Monitoring UI',
      'description' => 'Monitoring UI tests.',
      'group' => 'Monitoring',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Create the content type page in the setup as it is used by several tests.
    $this->drupalCreateContentType(array('type' => 'page'));
  }

  /**
   * Test the sensor settings UI.
   */
  public function testSensorSettingsUI() {
    $account = $this->drupalCreateUser(array('administer monitoring'));
    $this->drupalLogin($account);

    // The separate threshold settings tests have been split into separate
    // methods for better separation.
    $this->checkExceedsThresholdSettings();
    $this->checkFallsThresholdSettings();
    $this->checkInnerThresholdSettings();
    $this->checkOuterThresholdSettings();

    // Test that trying to access the sensors settings page of a non-existing
    // sensor results in a page not found response.
    $this->drupalGet('admin/config/system/monitoring/sensors/non_existing_sensor');
    $this->assertResponse(404);

    // Tests the fields 'Sensor Plugin' & 'Entity Type' appear.
    $this->drupalGet('admin/config/system/monitoring/sensors/user_new');
    $this->assertOptionSelected('edit-settings-old-entity-type', 'user');
    $this->assertText('Sensor Plugin');
    $this->assertText('Entity Aggregator');
  }

  /**
   * Tests creation of sensor through UI.
   */
  public function testSensorCreation() {
    $account = $this->drupalCreateUser(array('administer monitoring', 'monitoring reports'));
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/system/monitoring/sensors/add');

    $this->assertFieldByName('status', TRUE);

    $this->drupalPostForm('admin/config/system/monitoring/sensors/add', array(
      'label' => 'UI created Sensor',
      'description' => 'Sensor created to test UI',
      'id' => 'ui_test_sensor',
      'value_label' => 'Test Value',
      'caching_time' => 100,
      'sensor_id' => 'entity_aggregator',
    ), t('Select sensor'));

    $this->assertText('Sensor plugin settings');
    $this->drupalPostForm(NULL, array(
      'settings[time_interval_value]' => 86400,
      'settings[entity_type]' => 'field_storage_config',
      'settings[conditions][0][field]' => 'type',
      'settings[conditions][0][value]' => 'message',
    ), t('Save'));
    $this->assertText('Sensor settings saved.');

    $this->drupalGet('admin/config/system/monitoring/sensors/ui_test_sensor');
    $this->assertFieldByName('caching_time', 100);
    $this->assertFieldByName('settings[conditions][0][value]', 'message');

    $this->drupalGet('admin/config/system/monitoring/sensors/ui_test_sensor/delete');
    $this->assertText('This action cannot be undone.');
    $this->drupalPostForm(NULL, array(), t('Delete'));
    $this->assertText('Sensor UI created Sensor has been deleted.');

    $this->drupalGet('admin/config/system/monitoring/sensors/add');

    $this->drupalPostForm('admin/config/system/monitoring/sensors/add', array(
      'sensor_id' => 'config_value',
    ), t('Select sensor'));

    $this->assertText('Expected value');
    $this->assertFieldByName('settings[value]');
  }

  /**
   * Tests the time interval settings UI of the database aggregator sensor.
   */
  public function testAggregateSensorTimeIntervalConfig() {
    $account = $this->drupalCreateUser(array('administer monitoring', 'monitoring reports'));
    $this->drupalLogin($account);

    // Visit the overview and make sure the sensor is displayed.
    $this->drupalGet('admin/reports/monitoring');
    $this->assertText('0 druplicons in 1 day');

    $sensor_config = $this->sensorManager->getSensorConfigByName('db_aggregate_test');
    $this->drupalGet('admin/config/system/monitoring/sensors/db_aggregate_test');
    // Test for the default value.
    $this->assertFieldByName('settings[time_interval_value]', $sensor_config->getTimeIntervalValue());

    // Update the time interval and test for the updated value.
    $time_interval = 10800;
    $this->drupalPostForm('admin/config/system/monitoring/sensors/db_aggregate_test', array(
      'settings[time_interval_value]' => $time_interval,
    ), t('Save'));

    $this->sensorManager->resetCache();
    $sensor_config = $this->sensorManager->getSensorConfigByName('db_aggregate_test');
    $this->assertEqual($sensor_config->getTimeIntervalValue(), $time_interval);

    // Check the sensor overview to verify that the sensor result is
    // recalculated and the new sensor message is displayed.
    $this->drupalGet('admin/reports/monitoring');
    $this->assertText('0 druplicons in 3 hours');

    // Update the time interval and set it to no restriction.
    $time_interval = 0;
    $this->drupalPostForm('admin/config/system/monitoring/sensors/db_aggregate_test', array(
      'settings[time_interval_value]' => $time_interval,
    ), t('Save'));

    $this->sensorManager->resetCache();
    $sensor_config = $this->sensorManager->getSensorConfigByName('db_aggregate_test');
    $this->assertEqual($sensor_config->getTimeIntervalValue(), $time_interval);

    // Visit the overview and make sure that no time interval is displayed.
    $this->drupalGet('admin/reports/monitoring');
    $this->assertText('0 druplicons');
    $this->assertNoText('0 druplicons in');
  }

  /**
   * Sensor over page tests coverage.
   */
  public function testSensorOverviewPage() {
    $account = $this->drupalCreateUser(array('monitoring reports'));
    $this->drupalLogin($account);

    // Run the test_sensor and update the timestamp in the cache to make the
    // result the oldest.
    $this->runSensor('test_sensor');
    $cid = 'monitoring_sensor_result:test_sensor';
    $cache = \Drupal::cache('default')->get($cid);
    $cache->data['timestamp'] = $cache->data['timestamp'] - 1000;
    \Drupal::cache('default')->set(
      $cid,
      $cache->data,
      REQUEST_TIME + 3600,
      array('monitoring_sensor_result')
    );

    $this->drupalGet('admin/reports/monitoring');

    // Test if the Test sensor is listed as the oldest cached. We do not test
    // for the cached time as such test contains a risk of random fail.
    $this->assertRaw(String::format('Sensor %sensor (%category) cached before', array('%sensor' => 'Test sensor', '%category' => 'Test')));

    // Test the action buttons are clickable.
    $this->assertLink(t('Details'));
    $this->assertLink(t('Edit'));

    // Test the overview table.
    $tbody = $this->xpath('//table[@id="monitoring-sensors-overview"]/tbody');
    $rows = $tbody[0];
    $i = 0;
    foreach (monitoring_sensor_config_by_categories() as $category => $category_sensor_config) {
      $tr = $rows->tr[$i];
      $this->assertEqual($category, $tr->td->h3);
      foreach ($category_sensor_config as $sensor_config) {
        $i++;
        $tr = $rows->tr[$i];
        $this->assertEqual($tr->td[0]->span, $sensor_config->getLabel());
      }

      $i++;
    }

  }

  /**
   * Tests the sensor detail page.
   */
  public function testSensorDetailPage() {
    $account = $this->drupalCreateUser(array('monitoring reports', 'monitoring verbose', 'monitoring force run'));
    $this->drupalLogin($account);

    $this->drupalCreateNode(array('promote' => NODE_PROMOTED));

    $sensor_config = monitoring_sensor_manager()->getSensorConfigByName('db_aggregate_test');
    $this->drupalGet('admin/reports/monitoring/sensors/db_aggregate_test');
    $this->assertTitle(t('@label (@category) | Drupal', array('@label' => $sensor_config->getLabel(), '@category' => $sensor_config->getCategory())));

    // Make sure that all relevant information is displayed.
    // @todo: Assert position/order.
    // Cannot use $this->runSensor() as the cache needs to remain.
    $result = monitoring_sensor_run('db_aggregate_test');
    $this->assertText(t('Description'));
    $this->assertText($sensor_config->getDescription());
    $this->assertText(t('Status'));
    $this->assertText('Warning');
    $this->assertText(t('Message'));
    $this->assertText('1 druplicons in 1 day, falls below 2');
    $this->assertText(t('Execution time'));
    // The sensor is cached, so we have the same cached execution time.
    $this->assertText($result->getExecutionTime() . 'ms');
    $this->assertText(t('Cache information'));
    $this->assertText('Executed now, valid for 1 hour');
    $this->assertRaw(t('Run again'));

    $this->assertText(t('Verbose'));

    $this->assertText(t('Settings'));
    // @todo Add asserts about displayed settings once we display them in a
    //   better way.

    $this->assertText(t('Log'));

    $rows = $this->xpath('//div[contains(@class, "view-monitoring-sensor-results")]//tbody/tr');
    $this->assertEqual(count($rows), 1);
    $this->assertEqual(trim((string) $rows[0]->td[1]), 'WARNING');
    $this->assertEqual(trim((string) $rows[0]->td[2]), '1 druplicons in 1 day, falls below 2');

    // Create another node and run again.
    $this->drupalCreateNode(array('promote' => '1'));
    $this->drupalPostForm(NULL, array(), t('Run again'));
    $this->assertText('OK');
    $this->assertText('2 druplicons in 1 day');
    $rows = $this->xpath('//div[contains(@class, "view-monitoring-sensor-results")]//tbody/tr');
    $this->assertEqual(count($rows), 2);
    // The latest log result should be displayed first.
    $this->assertEqual(trim((string) $rows[0]->td[1]), 'OK');
    $this->assertEqual(trim((string) $rows[1]->td[1]), 'WARNING');

    // Refresh the page, this not run the sensor again.
    $this->drupalGet('admin/reports/monitoring/sensors/db_aggregate_test');
    $this->assertText('OK');
    $this->assertText('2 druplicons in 1 day');
    $this->assertText(t('Verbose output is not available for cached sensor results. Click force run to see verbose output.'));
    $rows = $this->xpath('//div[contains(@class, "view-monitoring-sensor-results")]//tbody/tr');
    $this->assertEqual(count($rows), 2);

    // Test the verbose output.
    $this->drupalPostForm(NULL, array(), t('Run now'));
    // Check that the verbose output is displayed.
    $this->assertText('Aggregate field nid');

    // Test that accessing a disabled or nisot-existing sensor results in a page
    // not found response.
    $this->sensorManager->disableSensor('test_sensor');
    $this->drupalGet('admin/reports/monitoring/sensors/test_sensor');
    $this->assertResponse(404);

    $this->drupalGet('admin/reports/monitoring/sensors/non_existing_sensor');
    $this->assertResponse(404);
  }
  /**
   * Tests the sensor detail page for actual and expected values.
   */
  public function testSensorEditPage() {
    $account = $this->drupalCreateUser(array('administer monitoring'));
    $this->drupalLogin($account);

    // Visit the edit page of "core theme default" (config value sensor)
    // and make sure the expected and actual values are displayed.
    $this->drupalGet('admin/config/system/monitoring/sensors/core_theme_default');
    $this->assertText('The expected value of config system.theme:default, actual value: classy');


    // Visit the edit page of "core maintainance mode" (state value sensor)
    // and make sure the expected and actual values are displayed.
    $this->drupalGet('admin/config/system/monitoring/sensors/core_maintenance_mode');
    $this->assertText('The expected value of state system.maintenance_mode, actual value: FALSE');

    // Visit the edit page of a sensor which has plugin that does not extend
    // ConfigurableSensorInterface to make sure the page works as intended.
    $this->drupalGet('admin/config/system/monitoring/sensors/core_requirements_node');

    // Test the checkbox in edit sensor settings for the bool sensor
    // Cron safe threshold enabled/disabled.
    $this->drupalGet('admin/config/system/monitoring/sensors/core_cron_safe_threshold');
    $this->assertNoFieldChecked('edit-settings-value');
    $this->drupalPostForm(NULL, array('settings[value]' => 'Checked'), t('Save'));

    $this->drupalGet('admin/config/system/monitoring/sensors/core_cron_safe_threshold');
    $this->assertFieldChecked('edit-settings-value');
  }

  /**
   * Tests the force execute all and sensor specific force execute links.
   */
  public function testForceExecute() {
    $account = $this->drupalCreateUser(array('monitoring force run', 'monitoring reports'));
    $this->drupalLogin($account);

    // Set a specific test sensor result to look for.
    $test_sensor_result_data = array(
      'sensor_message' => 'First message',
    );
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);

    $this->drupalGet('admin/reports/monitoring');
    $this->assertText('First message');

    // Update the sensor message.
    $test_sensor_result_data['sensor_message'] = 'Second message';
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);

    // Access the page again, we should still see the first message because the
    // cached result is returned.
    $this->drupalGet('admin/reports/monitoring');
    $this->assertText('First message');

    // Force sensor execution, the changed message should be displayed now.
    $this->clickLink(t('Force execute all'));
    $this->assertNoText('First message');
    $this->assertText('Second message');

    // Update the sensor message again.
    $test_sensor_result_data['sensor_message'] = 'Third message';
    \Drupal::state()->set('monitoring_test.sensor_result_data', $test_sensor_result_data);

    // Simulate a click on Force execution, there are many of those so we just
    // verify that such links exist and visit the path manually.
    $this->assertLink(t('Force execution'));
    $this->drupalGet('monitoring/sensors/force/test_sensor');
    $this->assertNoText('Second message');
    $this->assertText('Third message');

  }

  /**
   * Submits a threshold settings form for a given sensor.
   *
   * @param string $sensor_name
   *   The sensor name for the sensor that should be submitted.
   * @param array $thresholds
   *   Array of threshold values, keyed by the status, the value can be an
   *   integer or an array of integers for threshold types that need multiple
   *   values.
   */
  protected function submitThresholdSettings($sensor_name, array $thresholds) {
    $data = array();
    $sensor_config = $this->sensorManager->getSensorConfigByName($sensor_name);
    foreach ($thresholds as $key => $value) {
      $form_field_name = 'settings' . '[thresholds][' . $key . ']';
      $data[$form_field_name] = $value;
    }
    $this->drupalPostForm('admin/config/system/monitoring/sensors/' . $sensor_config->getName(), $data, t('Save'));
  }

  /**
   * Asserts that defaults are set correctly in the settings form.
   *
   * @param string $sensor_name
   *   The sensor name for the sensor that should be submitted.
   * @param array $thresholds
   *   Array of threshold values, keyed by the status, the value can be an
   *   integer or an array of integers for threshold types that need multiple
   *   values.
   */
  protected function assertThresholdSettingsUIDefaults($sensor_name, $thresholds) {
    $sensor_config = $this->sensorManager->getSensorConfigByName($sensor_name);
    $this->drupalGet('admin/config/system/monitoring/sensors/' . $sensor_name);
    $this->assertTitle(t('@label settings (@category) | Drupal', array('@label' => $sensor_config->getLabel(), '@category' => $sensor_config->getCategory())));
    foreach ($thresholds as $key => $value) {
      $form_field_name = 'settings' . '[thresholds][' . $key . ']';
      $this->assertFieldByName($form_field_name, $value);
    }
  }

  /**
   * Tests exceeds threshold settings UI and validation.
   */
  protected function checkExceedsThresholdSettings() {
    // Test with valid values.
    $thresholds = array(
      'critical' => 11,
      'warning' => 6,
    );
    $this->submitThresholdSettings('test_sensor_exceeds', $thresholds);
    $this->assertText(t('Sensor settings saved.'));
    $this->assertThresholdSettingsUIDefaults('test_sensor_exceeds', $thresholds);

    // Make sure that it is possible to save empty thresholds.
    $thresholds = array(
      'critical' => '',
      'warning' => '',
    );
    $this->submitThresholdSettings('test_sensor_exceeds', $thresholds);
    $this->assertText(t('Sensor settings saved.'));
    $this->assertThresholdSettingsUIDefaults('test_sensor_exceeds', $thresholds);

    $this->sensorManager->resetCache();
    \Drupal::service('monitoring.sensor_runner')->resetCache();
    $sensor_result = $this->runSensor('test_sensor_exceeds');
    $this->assertTrue($sensor_result->isOk());

    // Test validation.
    $thresholds = array(
      'critical' => 5,
      'warning' => 10,
    );
    $this->submitThresholdSettings('test_sensor_exceeds', $thresholds);
    $this->assertText('Warning must be lower than critical or empty.');

    $thresholds = array(
      'critical' => 5,
      'warning' => 5,
    );
    $this->submitThresholdSettings('test_sensor_exceeds', $thresholds);
    $this->assertText('Warning must be lower than critical or empty.');

    $thresholds = array(
      'critical' => 'alphanumeric',
      'warning' => 'alphanumeric',
    );
    $this->submitThresholdSettings('test_sensor_exceeds', $thresholds);
    $this->assertText('Warning must be a number.');
    $this->assertText('Critical must be a number.');
    return $thresholds;
  }

  /**
   * Tests falls threshold settings UI and validation.
   */
  protected function checkFallsThresholdSettings() {
    // Test with valid values.
    $thresholds = array(
      'critical' => 6,
      'warning' => 11,
    );
    $this->submitThresholdSettings('test_sensor_falls', $thresholds);
    $this->assertText(t('Sensor settings saved.'));
    $this->assertThresholdSettingsUIDefaults('test_sensor_falls', $thresholds);

    // Make sure that it is possible to save empty thresholds.
    $thresholds = array(
      'critical' => '',
      'warning' => '',
    );
    $this->submitThresholdSettings('test_sensor_falls', $thresholds);
    $this->assertText(t('Sensor settings saved.'));
    $this->assertThresholdSettingsUIDefaults('test_sensor_falls', $thresholds);

    // Test validation.
    $thresholds = array(
      'critical' => 50,
      'warning' => 45,
    );
    $this->submitThresholdSettings('test_sensor_falls', $thresholds);
    $this->assertText('Warning must be higher than critical or empty.');

    $thresholds = array(
      'critical' => 5,
      'warning' => 5,
    );
    $this->submitThresholdSettings('test_sensor_falls', $thresholds);
    $this->assertText('Warning must be higher than critical or empty.');

    $thresholds = array(
      'critical' => 'alphanumeric',
      'warning' => 'alphanumeric',
    );
    $this->submitThresholdSettings('test_sensor_falls', $thresholds);
    $this->assertText('Warning must be a number.');
    $this->assertText('Critical must be a number.');
    return $thresholds;
  }

  /**
   * Tests inner threshold settings UI and validation.
   */
  protected function checkInnerThresholdSettings() {
    // Test with valid values.
    $thresholds = array(
      'critical_low' => 5,
      'warning_low' => 1,
      'critical_high' => 10,
      'warning_high' => 15,
    );
    $this->submitThresholdSettings('test_sensor_inner', $thresholds);
    $this->assertText(t('Sensor settings saved.'));
    $this->assertThresholdSettingsUIDefaults('test_sensor_inner', $thresholds);

    // Make sure that it is possible to save empty inner thresholds.
    $thresholds = array(
      'critical_low' => '',
      'warning_low' => '',
      'critical_high' => '',
      'warning_high' => '',
    );
    $this->submitThresholdSettings('test_sensor_inner', $thresholds);
    $this->assertText(t('Sensor settings saved.'));
    $this->assertThresholdSettingsUIDefaults('test_sensor_inner', $thresholds);

    // Test validation.
    $thresholds = array(
      'critical_low' => 5,
      'warning_low' => 15,
      'critical_high' => 10,
      'warning_high' => 20,
    );
    $this->submitThresholdSettings('test_sensor_inner', $thresholds);
    $this->assertText('Warning low must be lower than critical low or empty.');

    $thresholds = array(
      'critical_low' => 5,
      'warning_low' => 5,
      'critical_high' => 5,
      'warning_high' => 5,
    );
    $this->submitThresholdSettings('test_sensor_inner', $thresholds);
    $this->assertText('Warning low must be lower than warning high or empty.');

    $thresholds = array(
      'critical_low' => 50,
      'warning_low' => 95,
      'critical_high' => 55,
      'warning_high' => 100,
    );
    $this->submitThresholdSettings('test_sensor_inner', $thresholds);
    $this->assertText('Warning low must be lower than critical low or empty.');

    $thresholds = array(
      'critical_low' => 'alphanumeric',
      'warning_low' => 'alphanumeric',
      'critical_high' => 'alphanumeric',
      'warning_high' => 'alphanumeric',
    );
    $this->submitThresholdSettings('test_sensor_inner', $thresholds);
    $this->assertText('Warning low must be a number.');
    $this->assertText('Warning high must be a number.');
    $this->assertText('Critical low must be a number.');
    $this->assertText('Critical high must be a number.');

    $thresholds = array(
      'critical_low' => 45,
      'warning_low' => 35,
      'critical_high' => 50,
      'warning_high' => 40,
    );
    $this->submitThresholdSettings('test_sensor_inner', $thresholds);
    $this->assertText('Warning high must be higher than critical high or empty.');
    return $thresholds;
  }

  /**
   * Tests outer threshold settings UI and validation.
   */
  protected function checkOuterThresholdSettings() {
    // Test with valid values.
    $thresholds = array(
      'critical_low' => 5,
      'warning_low' => 6,
      'critical_high' => 15,
      'warning_high' => 14,
    );
    $this->submitThresholdSettings('test_sensor_outer', $thresholds);
    $this->assertText(t('Sensor settings saved.'));
    $this->assertThresholdSettingsUIDefaults('test_sensor_outer', $thresholds);

    // Make sure that it is possible to save empty outer thresholds.
    $thresholds = array(
      'critical_low' => '',
      'warning_low' => '',
      'critical_high' => '',
      'warning_high' => '',
    );
    $this->submitThresholdSettings('test_sensor_outer', $thresholds);
    $this->assertText(t('Sensor settings saved.'));
    $this->assertThresholdSettingsUIDefaults('test_sensor_outer', $thresholds);

    // Test validation.
    $thresholds = array(
      'critical_low' => 5,
      'warning_low' => 15,
      'critical_high' => 10,
      'warning_high' => 20,
    );
    $this->submitThresholdSettings('test_sensor_outer', $thresholds);
    $this->assertText('Warning high must be lower than critical high or empty.');

    $thresholds = array(
      'critical_low' => 5,
      'warning_low' => 5,
      'critical_high' => 5,
      'warning_high' => 5,
    );
    $this->submitThresholdSettings('test_sensor_outer', $thresholds);
    $this->assertText('Warning low must be lower than warning high or empty.');

    $thresholds = array(
      'critical_low' => 'alphanumeric',
      'warning_low' => 'alphanumeric',
      'critical_high' => 'alphanumeric',
      'warning_high' => 'alphanumeric',
    );
    $this->submitThresholdSettings('test_sensor_outer', $thresholds);
    $this->assertText('Warning low must be a number.');
    $this->assertText('Warning high must be a number.');
    $this->assertText('Critical low must be a number.');
    $this->assertText('Critical high must be a number.');

    $thresholds = array(
      'critical_low' => 45,
      'warning_low' => 35,
      'critical_high' => 45,
      'warning_high' => 35,
    );
    $this->submitThresholdSettings('test_sensor_outer', $thresholds);
    $this->assertText('Warning low must be lower than warning high or empty.');

    $thresholds = array(
      'critical_low' => 50,
      'warning_low' => 95,
      'critical_high' => 55,
      'warning_high' => 100,
    );
    $this->submitThresholdSettings('test_sensor_outer', $thresholds);
    $this->assertText('Warning high must be lower than critical high or empty.');
  }

}
