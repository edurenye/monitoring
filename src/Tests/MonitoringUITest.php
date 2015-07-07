<?php
/**
 * @file
 * Contains \Drupal\monitoring\Tests\MonitoringUITest.
 */

namespace Drupal\monitoring\Tests;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\monitoring\Entity\SensorConfig;

/**
 * Tests for the Monitoring UI.
 *
 * @group monitoring
 */
class MonitoringUITest extends MonitoringTestBase {

  public static $modules = array('dblog', 'node', 'views');

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Create the content type page in the setup as it is used by several tests.
    $this->drupalCreateContentType(array('type' => 'page'));
  }

  /**
   * Test the monitoring settings UI.
   */
  public function testSettingsUI() {
    // Create a test user and log in.
    $account = $this->drupalCreateUser(array(
      'access administration pages',
      'administer monitoring',
    ));
    $this->drupalLogin($account);

    // Check the form.
    $this->drupalGet('admin/config/system');
    $this->assertText(t('Configure enabled monitoring products.'));
    $this->clickLink(t('Monitoring settings'));
    $this->assertField('sensor_call_logging');
    $this->assertOptionSelected('edit-sensor-call-logging', 'on_request');
    $this->assertText(t('Control local logging of sensor call results.'));
  }

  /**
   * Test the sensor settings UI.
   */
  public function testSensorSettingsUI() {
    $account = $this->drupalCreateUser(array('administer monitoring'));
    $this->drupalLogin($account);

    // The separate threshold settings tests have been split into separate
    // methods for better separation.
    $this->doTestExceedsThresholdSettings();
    $this->doTestFallsThresholdSettings();
    $this->doTestInnerThresholdSettings();
    $this->doTestOuterThresholdSettings();

    // Test that trying to access the sensors settings page of a non-existing
    // sensor results in a page not found response.
    $this->drupalGet('admin/config/system/monitoring/sensors/non_existing_sensor');
    $this->assertResponse(404);

    // Tests the fields 'Sensor Plugin' & 'Entity Type' appear.
    $this->drupalGet('admin/config/system/monitoring/sensors/user_new');
    $this->assertOptionSelected('edit-settings-entity-type', 'user');
    $this->assertText('Sensor Plugin');
    $this->assertText('Entity Aggregator');

    // Tests adding a condition to the log out sensor.
    $this->drupalGet('admin/config/system/monitoring/sensors/user_session_logouts');
    $edit = array(
      'conditions[2][field]' => 'severity',
      'conditions[2][value]' => 5,
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalGet('admin/config/system/monitoring/sensors/user_session_logouts');
    $this->assertFieldByName('conditions[2][field]', 'severity');
    $this->assertFieldByName('conditions[2][value]', 5);
  }

  /**
   * Tests creation of sensor through UI.
   */
  public function testSensorCreation() {
    $account = $this->drupalCreateUser(array('administer monitoring', 'monitoring reports'));
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/system/monitoring/sensors/add');

    $this->assertFieldByName('status', TRUE);

    $this->drupalPostForm(NULL, array(
      'label' => 'UI created Sensor',
      'id' => 'ui_test_sensor',
      'plugin_id' => 'entity_aggregator',
    ), t('Select sensor'));

    $this->assertText('Sensor plugin settings');
    $this->drupalPostForm(NULL, array('settings[entity_type]' => 'field_storage_config'), t('Add more conditions'));
    $this->drupalPostForm(NULL, array(
      'description' => 'Sensor created to test UI',
      'value_label' => 'Test Value',
      'caching_time' => 100,
      'settings[aggregation][time_interval_value]' => 86400,
      'settings[entity_type]' => 'field_storage_config',
      'conditions[0][field]' => 'type',
      'conditions[0][value]' => 'message',
      'conditions[1][field]' => 'parent',
      'conditions[1][value]' => 'article',
    ), t('Save'));

    $this->assertText(SafeMarkup::format('Sensor @label saved.', array('@label' => 'UI created Sensor')));

    // Test details page by clicking the link in confirmation message.
    $this->assertLink(t('UI created Sensor'));
    $this->clickLink(t('UI created Sensor'));
    $this->assertText('Result');

    $this->drupalGet('admin/config/system/monitoring/sensors/ui_test_sensor');
    $this->assertFieldByName('caching_time', 100);
    $this->assertFieldByName('conditions[0][value]', 'message');
    $this->assertFieldByName('conditions[1][value]', 'article');

    $this->drupalGet('admin/config/system/monitoring/sensors/ui_test_sensor/delete');
    $this->assertText('This action cannot be undone.');
    $this->drupalPostForm(NULL, array(), t('Delete'));
    $this->assertText('Sensor UI created Sensor has been deleted.');

    $this->drupalPostForm('admin/config/system/monitoring/sensors/add', array(
      'label' => 'UI created Sensor config',
      'id' => 'ui_test_sensor_config',
      'plugin_id' => 'config_value',
    ), t('Select sensor'));

    $this->assertText('Expected value');

    $this->assertText('Sensor plugin settings');
    $this->drupalPostForm(NULL, array(
      'description' => 'Sensor created to test UI',
      'value_label' => 'Test Value',
      'caching_time' => 100,
      'value_type' => 'bool',
      'settings[key]' => 'threshold.autorun',
      'settings[config]' => 'system.cron',
    ), t('Save'));
    $this->assertText(SafeMarkup::format('Sensor @label saved.', array('@label' => 'UI created Sensor config')));

    // Go back to the sensor edit page,
    // Check the value type is properly selected.
    $this->drupalGet('admin/config/system/monitoring/sensors/ui_test_sensor_config');
    $this->assertOptionSelected('edit-value-type', 'bool');

    // Try to enable a sensor which is disabled by default and vice versa.
    // Check the default status of cron safe threshold and new users sensors.
    $sensor_cron = SensorConfig::load('core_cron_safe_threshold');
    $this->assertTrue($sensor_cron->status());
    $sensor_comment = SensorConfig::load('user_new');
    $this->assertFalse($sensor_comment->status());

    // Change the status of these sensors.
    $this->drupalPostForm('admin/config/system/monitoring/sensors', array(
      'sensors[core_cron_safe_threshold]' => FALSE,
      'sensors[user_new]' => TRUE,
    ), t('Update enabled sensors'));

    // Make sure the changes have been made.
    $sensor_cron = SensorConfig::load('core_cron_safe_threshold');
    $this->assertFalse($sensor_cron->status());
    $sensor_comment = SensorConfig::load('user_new');
    $this->assertTrue($sensor_comment->status());

  }

  /**
   * Tests the entity aggregator sensors.
   *
   * Tests the entity aggregator with time interval settings and verbosity.
   *
   * @todo add tests for DB aggregator.
   */
  public function testAggregateSensorTimeIntervalConfig() {
    $account = $this->drupalCreateUser(array('administer monitoring', 'monitoring reports', 'monitoring reports'));
    $this->drupalLogin($account);

    // Create some nodes.
    $node1 = $this->drupalCreateNode(array('type' => 'page'));
    $node2 = $this->drupalCreateNode(array('type' => 'page'));

    // Visit the overview and make sure the sensor is displayed.
    $this->drupalGet('admin/reports/monitoring');
    $this->assertText('2 druplicons in 1 day');

    // Visit the sensor edit form.
    $this->drupalGet('admin/config/system/monitoring/sensors/entity_aggregate_test');
    // Test for the default value.
    $this->assertFieldByName('settings[aggregation][time_interval_field]', 'created');
    $this->assertFieldByName('settings[aggregation][time_interval_value]', 86400);

    // Visit the sensor detail page with verbose output.
    $this->drupalGet('admin/reports/monitoring/sensors/entity_aggregate_test');
    // Check that there is no Save button on the detail page.
    $this->assertNoLink('Save');
    $this->drupalPostForm(NULL, array(), 'Run now');
    // The node labels should appear in verbose output.
    $this->assertText('Label');
    $this->assertLink($node1->getTitle());
    $this->assertLink($node2->getTitle());

    // Check the sensor overview to verify that the sensor result is
    // calculated and the sensor message is displayed.
    $this->drupalGet('admin/reports/monitoring');
    $this->assertText('2 druplicons in 1 day');

    // Update the time interval and set value to no restriction.
    $this->drupalPostForm('admin/config/system/monitoring/sensors/entity_aggregate_test', array(
      'settings[aggregation][time_interval_value]' => 0,
    ), t('Save'));

    // Visit the overview and make sure that no time interval is displayed.
    $this->drupalGet('admin/reports/monitoring');
    $this->assertText('2 druplicons');
    $this->assertNoText('2 druplicons in');

    // Update the time interval and empty interval field.
    $this->drupalPostForm('admin/config/system/monitoring/sensors/entity_aggregate_test', array(
      'settings[aggregation][time_interval_field]' => '',
      'settings[aggregation][time_interval_value]' => 86400,
    ), t('Save'));
    // Visit the overview and make sure that no time interval is displayed
    // which also make sures no change in time interval applies.
    $this->drupalGet('admin/reports/monitoring');
    $this->assertText('2 druplicons');
    $this->assertNoText('2 druplicons in');

    // Update the time interval field with an invalid value.
    $this->drupalPostForm('admin/config/system/monitoring/sensors/entity_aggregate_test', array(
      'settings[aggregation][time_interval_field]' => 'invalid-field',
    ), t('Save'));
    // Assert the error message.
    $this->assertText('The specified time interval field invalid-field does not exist or is not type timestamp.');
  }

  /**
   * Tests the sensor results overview and the global sensor log.
   */
  public function testSensorOverviewPage() {
    // Check access for the overviews.
    $this->drupalGet('admin/reports/monitoring');
    $this->assertResponse(403);
    $this->drupalGet('admin/reports/monitoring/log');
    $this->assertResponse(403);
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
    $this->assertRaw(SafeMarkup::format('Sensor %sensor (%category) cached before', array('%sensor' => 'Test sensor', '%category' => 'Test')));

    // Assert if .js & .css are loaded.
    $this->assertRaw('monitoring.js');
    $this->assertRaw('monitoring.css');

    // Test the action buttons are clickable.
    $this->assertLink(t('Details'));
    $this->assertLink(t('Edit'));
    $this->assertLink(t('Details'));

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

    // Test the global sensor log.
    $this->clickLink(t('Log'));
    $this->assertText('test_sensor');
    $this->assertText(t('OK'));
    $this->assertText(t('No value'));
    $this->assertRaw('class="monitoring-ok"');
    $this->clickLink('test_sensor');
    $this->assertResponse(200);
    $this->assertUrl(SensorConfig::load('test_sensor')->urlInfo('details-form'));
  }

  /**
   * Tests the sensor detail page.
   */
  public function testSensorDetailPage() {
    $account = $this->drupalCreateUser(array('monitoring reports', 'monitoring verbose', 'monitoring force run'));
    $this->drupalLogin($account);

    $this->drupalCreateNode(array('promote' => NODE_PROMOTED));

    $sensor_config = SensorConfig::load('entity_aggregate_test');
    $this->drupalGet('admin/reports/monitoring/sensors/entity_aggregate_test');
    $this->assertTitle(t('@label (@category) | Drupal', array('@label' => $sensor_config->getLabel(), '@category' => $sensor_config->getCategory())));

    // Make sure that all relevant information is displayed.
    // @todo: Assert position/order.
    // Cannot use $this->runSensor() as the cache needs to remain.
    $result = monitoring_sensor_run('entity_aggregate_test');
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
    $this->assertTrue(preg_match('/\b' . 'monitoring-ok' . '\b/', $rows[0]->attributes()['class']));
    $this->assertEqual(trim((string) $rows[1]->td[1]), 'WARNING');
    $this->assertTrue(preg_match('/\b' . 'monitoring-warning' . '\b/', $rows[1]->attributes()['class']));

    // Refresh the page, this not run the sensor again.
    $this->drupalGet('admin/reports/monitoring/sensors/entity_aggregate_test');
    $this->assertText('OK');
    $this->assertText('2 druplicons in 1 day');
    $this->assertText(t('Verbose output is not available for cached sensor results. Click force run to see verbose output.'));
    $rows = $this->xpath('//div[contains(@class, "view-monitoring-sensor-results")]//tbody/tr');
    $this->assertEqual(count($rows), 2);

    // Test the verbose output.
    $this->drupalPostForm(NULL, array(), t('Run now'));
    // Check that the verbose output is displayed.
    $this->assertText('Aggregate field');
    $this->assertText('nid');

    // Check the if the sensor message includes value type.
    $this->drupalGet('admin/reports/monitoring/sensors/core_cron_safe_threshold');
    $this->assertText('FALSE');

    // Test that accessing a disabled or nisot-existing sensor results in an
    // access denied and a page not found response.
    monitoring_sensor_manager()->disableSensor('test_sensor');
    $this->drupalGet('admin/reports/monitoring/sensors/test_sensor');
    $this->assertResponse(403);

    $this->drupalGet('admin/reports/monitoring/sensors/non_existing_sensor');
    $this->assertResponse(404);

    // Test user integrity sensor detail page.
    $this->drupalGet('admin/reports/monitoring/sensors/user_integrity');
    $this->assertText('1 privileged user(s)');

    $test_user = $this->drupalCreateUser(array('administer monitoring'), 'test_user');
    $test_user->save();
    $this->drupalLogin($test_user);
    $this->runSensor('user_integrity');
    $this->drupalGet('admin/reports/monitoring/sensors/user_integrity');
    $this->assertText('2 privileged user(s), 1 new user(s)');
    // Run the sensor to check verbose output.
    $this->drupalPostForm(NULL, array(), t('Run now'));
    // Check the new user name in verbose output.
    $this->assertText('test_user');
    // Reset the user data and run the sensor again.
    $this->drupalPostForm('admin/config/system/monitoring/sensors/user_integrity', array(), t('Reset user data'));
    $this->runSensor('user_integrity');
    $this->drupalGet('admin/reports/monitoring/sensors/user_integrity');
    $this->assertText('2 privileged user(s)');

    // Change user data and run sensor.
    $test_user->setUsername('changed_name');
    $test_user->save();
    $this->runSensor('user_integrity');
    $this->drupalGet('admin/reports/monitoring/sensors/user_integrity');
    $this->assertText('2 privileged user(s), 1 changed user(s)');

    // Reset user data again and check sensor message.
    $this->drupalPostForm('admin/config/system/monitoring/sensors/user_integrity', array(), t('Reset user data'));
    $this->runSensor('user_integrity');
    $this->drupalGet('admin/reports/monitoring/sensors/user_integrity');
    $this->assertText('2 privileged user(s)');
  }

  /**
   * Tests the sensor detail page for actual and expected values.
   */
  public function testSensorEditPage() {
    $account = $this->drupalCreateUser(array('administer monitoring', 'monitoring reports'));
    $this->drupalLogin($account);

    // Visit the edit page of "core theme default" (config value sensor)
    // and make sure the expected and current values are displayed.
    $this->drupalGet('admin/config/system/monitoring/sensors/core_theme_default');
    $this->assertText('The expected value of config system.theme:default, current value: classy');


    // Visit the edit page of "core maintainance mode" (state value sensor)
    // and make sure the expected and current values are displayed.
    $this->drupalGet('admin/config/system/monitoring/sensors/core_maintenance_mode');
    $this->assertText('The expected value of state system.maintenance_mode, current value: FALSE');
    // Make sure delete link is not available for this sensor.
    $this->assertNoLink(t('Delete'));
    // Make sure details page is available for an enabled sensor.
    $this->assertLink('Details');

    // Test the checkbox in edit sensor settings for the bool sensor
    // Cron safe threshold enabled/disabled.
    $this->drupalGet('admin/config/system/monitoring/sensors/core_cron_safe_threshold');
    // Make sure delete action available for this sensor.
    $this->assertLink(t('Delete'));
    $this->assertNoFieldChecked('edit-settings-value');
    $this->drupalPostForm(NULL, array('settings[value]' => 'Checked'), t('Save'));

    $this->drupalGet('admin/config/system/monitoring/sensors/core_cron_safe_threshold');
    $this->assertFieldChecked('edit-settings-value');

    // Test whether the details page is available.
    $this->assertLink(t('Details'));
    $this->clickLink(t('Details'));
    $this->assertText('Result');

    $this->assertLink(t('Edit'));
    $this->clickLink(t('Edit'));
    $this->assertText('Sensor plugin settings');

    // Test detail page is not available for a disabled sensor.
    $this->drupalGet('admin/config/system/monitoring/sensors/node_new_all');
    $this->assertNoLink('Details');

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
   * Tests the UI of the requirements sensor.
   */
  public function testCoreRequirementsSensorUI() {
    $account = $this->drupalCreateUser(array('administer monitoring'));
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/system/monitoring/sensors/core_requirements_system');

    // Verify the current keys to exclude.
    $this->assertText('cron');

    // Change the excluded keys.
    $this->drupalPostForm(NULL, array(
      'settings[exclude_keys]' => 'requirement_excluded'
    ),  t('Save'));

    $this->assertText(SafeMarkup::format('Sensor @label saved.', array('@label' => 'Module system')));
    $this->drupalGet('admin/config/system/monitoring/sensors/core_requirements_system');
    // Verify the change in excluded keys.
    $this->assertText('requirement_excluded');
    $this->assertNoText('cron');


   // $this->drupalGet('admin/config/system/monitoring/sensors/ui_test_sensor');
  }

  /**
   * Tests the auto completion of the sensor category field.
   */
  public function testAutoComplete() {
    $account = $this->drupalCreateUser(array('administer monitoring'));
    $this->drupalLogin($account);

    // Test with "C", which matches Content and Cron.
    $categories = $this->drupalGetJSON('/monitoring-category/autocomplete', array('query' => array('q' => 'C')));
    $this->assertEqual(count($categories), 2, '2 autocomplete suggestions.');
    $this->assertEqual('Content', $categories[0]['label']);
    $this->assertEqual('Cron', $categories[1]['label']);

    // Check that a non-matching prefix returns no suggestions.
    $categories = $this->drupalGetJSON('/monitoring-category/autocomplete', array('query' => array('q' => 'non_existing_category')));
    $this->assertTrue(empty($categories), 'No autocomplete suggestions for non-existing query string.');
  }

  /**
   * Tests the UI/settings of the installed modules sensor.
   *
   * @see InstalledModulesSensorPlugin
   */
  public function testSensorInstalledModulesUI() {
    $account = $this->drupalCreateUser(array('administer monitoring'));
    $this->drupalLogin($account);

    // Visit settings page of the disabled sensor. We run the sensor to check
    // for deltas. This led to fatal errors with a disabled sensor.
    $this->drupalGet('admin/config/system/monitoring/sensors/monitoring_installed_modules');

    // Enable the sensor.
    monitoring_sensor_manager()->enableSensor('monitoring_installed_modules');

    // Test submitting the defaults and enabling the sensor.
    $this->drupalPostForm('admin/config/system/monitoring/sensors/monitoring_installed_modules', array(
      'status' => TRUE,
    ), t('Save'));
    // Reset the sensor config so that it reflects changes done via POST.
    monitoring_sensor_manager()->resetCache();
    // The sensor should now be OK.
    $result = $this->runSensor('monitoring_installed_modules');
    $this->assertTrue($result->isOk());

    // Expect the contact and book modules to be installed.
    $this->drupalPostForm('admin/config/system/monitoring/sensors/monitoring_installed_modules', array(
      'settings[modules][contact]' => TRUE,
      'settings[modules][book]' => TRUE,
    ), t('Save'));
    // Reset the sensor config so that it reflects changes done via POST.
    monitoring_sensor_manager()->resetCache();
    // Make sure the extended / hidden_modules form submit cleanup worked and
    // they are not stored as a duplicate in settings.
    $sensor_config = SensorConfig::load('monitoring_installed_modules');
    $this->assertTrue(!array_key_exists('extended', $sensor_config->settings), 'Do not persist extended module hidden selections separately.');
    // The sensor should escalate to CRITICAL.
    $result = $this->runSensor('monitoring_installed_modules');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getMessage(), '2 modules delta, expected 0, Following modules are expected to be installed: Book (book), Contact (contact)');
    $this->assertEqual($result->getValue(), 2);

    // Reset modules selection with the update selection (ajax) button.
    $this->drupalGet('admin/config/system/monitoring/sensors/monitoring_installed_modules');
    $this->drupalPostAjaxForm(NULL, array(), array('op' => t('Update module selection')));
    $this->drupalPostForm(NULL, array(), t('Save'));
    $result = $this->runSensor('monitoring_installed_modules');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), '0 modules delta');

    // The default setting is not to allow additional modules. Enable comment
    // and the sensor should escalate to CRITICAL.
    $this->installModules(array('help'));
    // The container is rebuilt and needs to be reassigned to avoid static
    // config cache issues. See https://www.drupal.org/node/2398867
    $this->container = \Drupal::getContainer();
    $result = $this->runSensor('monitoring_installed_modules');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getMessage(), '1 modules delta, expected 0, Following modules are NOT expected to be installed: Help (help)');
    $this->assertEqual($result->getValue(), 1);
    // Allow additional, the sensor should not escalate anymore.
    $this->drupalPostForm('admin/config/system/monitoring/sensors/monitoring_installed_modules', array(
      'settings[allow_additional]' => 1,
    ), t('Save'));
    $result = $this->runSensor('monitoring_installed_modules');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), '0 modules delta');
  }

  /**
   * UI Tests for disappearing sensors.
   *
   * We provide a separate test method for the DisappearedSensorsSensorPlugin as we
   * need to install and uninstall additional modules.
   *
   * @see DisappearedSensorsSensorPlugin
   */
  public function testSensorDisappearedSensorsUI() {
    $account = $this->drupalCreateUser(array('administer monitoring'));
    $this->drupalLogin($account);

    // Install comment module and the comment_new sensor.
    $this->installModules(array('comment'));
    monitoring_sensor_manager()->enableSensor('comment_new');

    // We should have the message that no sensors are missing.
    $this->drupalGet('admin/config/system/monitoring/sensors/monitoring_disappeared_sensors');
    $this->assertNoText(t('This action will clear the missing sensors and the critical sensor status will go away.'));

    // Disable sensor and the ininstall comment module. This is the correct
    // procedure and therefore there should be no missing sensors.
    monitoring_sensor_manager()->disableSensor('comment_new');
    $this->drupalGet('admin/config/system/monitoring/sensors/monitoring_disappeared_sensors');
    $this->assertNoText(t('This action will clear the missing sensors and the critical sensor status will go away.'));

    // Install comment module and the comment_new sensor.
    $this->installModules(array('comment'));
    monitoring_sensor_manager()->enableSensor('comment_new');
    // Now uninstall the comment module to have the comment_new sensor disappear.
    $this->uninstallModules(array('comment'));
    // Run the monitoring_disappeared_sensors sensor to get the status message
    // that should be found in the settings form.
    $this->drupalGet('admin/config/system/monitoring/sensors/monitoring_disappeared_sensors');
    $this->assertText('Missing sensor comment_new');

    // Now reset the sensor list - we should get the "no missing sensors"
    // message.
    $this->drupalPostForm(NULL, array(), t('Clear missing sensors'));
    $this->assertText(t('All missing sensors have been cleared.'));
    $this->drupalGet('admin/config/system/monitoring/sensors/monitoring_disappeared_sensors');
    $this->assertNoText('Missing sensor comment_new');
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
    $sensor_config = SensorConfig::load($sensor_name);
    foreach ($thresholds as $key => $value) {
      $form_field_name = 'thresholds[' . $key . ']';
      $data[$form_field_name] = $value;
    }
    $this->drupalPostForm('admin/config/system/monitoring/sensors/' . $sensor_config->id(), $data, t('Save'));
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
    $sensor_config = SensorConfig::load($sensor_name);
    $this->drupalGet('admin/config/system/monitoring/sensors/' . $sensor_name);
    $this->assertTitle(t('@label settings (@category) | Drupal', array('@label' => $sensor_config->getLabel(), '@category' => $sensor_config->getCategory())));
    foreach ($thresholds as $key => $value) {
      $form_field_name = 'thresholds[' . $key . ']';
      $this->assertFieldByName($form_field_name, $value);
    }
  }

  /**
   * Tests exceeds threshold settings UI and validation.
   */
  protected function doTestExceedsThresholdSettings() {
    // Test with valid values.
    $thresholds = array(
      'critical' => 11,
      'warning' => 6,
    );
    $this->submitThresholdSettings('test_sensor_exceeds', $thresholds);
    $this->assertText(SafeMarkup::format('Sensor @label saved.', array('@label' => 'Test sensor exceeds')));
    $this->assertThresholdSettingsUIDefaults('test_sensor_exceeds', $thresholds);

    // Make sure that it is possible to save empty thresholds.
    $thresholds = array(
      'critical' => '',
      'warning' => '',
    );
    $this->submitThresholdSettings('test_sensor_exceeds', $thresholds);
    $this->assertText(SafeMarkup::format('Sensor @label saved.', array('@label' => 'Test sensor exceeds')));
    $this->assertThresholdSettingsUIDefaults('test_sensor_exceeds', $thresholds);

    monitoring_sensor_manager()->resetCache();
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
  protected function doTestFallsThresholdSettings() {
    // Test with valid values.
    $thresholds = array(
      'critical' => 6,
      'warning' => 11,
    );
    $this->submitThresholdSettings('test_sensor_falls', $thresholds);
    $this->assertText(SafeMarkup::format('Sensor @label saved.', array('@label' => 'Test sensor falls')));
    $this->assertThresholdSettingsUIDefaults('test_sensor_falls', $thresholds);

    // Make sure that it is possible to save empty thresholds.
    $thresholds = array(
      'critical' => '',
      'warning' => '',
    );
    $this->submitThresholdSettings('test_sensor_falls', $thresholds);
    $this->assertText(SafeMarkup::format('Sensor @label saved.', array('@label' => 'Test sensor falls')));
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
  protected function doTestInnerThresholdSettings() {
    // Test with valid values.
    $thresholds = array(
      'critical_low' => 5,
      'warning_low' => 1,
      'critical_high' => 10,
      'warning_high' => 15,
    );
    $this->submitThresholdSettings('test_sensor_inner', $thresholds);
    $this->assertText(SafeMarkup::format('Sensor @label saved.', array('@label' => 'Test sensor inner')));
    $this->assertThresholdSettingsUIDefaults('test_sensor_inner', $thresholds);

    // Make sure that it is possible to save empty inner thresholds.
    $thresholds = array(
      'critical_low' => '',
      'warning_low' => '',
      'critical_high' => '',
      'warning_high' => '',
    );
    $this->submitThresholdSettings('test_sensor_inner', $thresholds);
    $this->assertText(SafeMarkup::format('Sensor @label saved.', array('@label' => 'Test sensor inner')));
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
  protected function doTestOuterThresholdSettings() {
    // Test with valid values.
    $thresholds = array(
      'critical_low' => 5,
      'warning_low' => 6,
      'critical_high' => 15,
      'warning_high' => 14,
    );
    $this->submitThresholdSettings('test_sensor_outer', $thresholds);
    $this->assertText(SafeMarkup::format('Sensor @label saved.', array('@label' => 'Test sensor outer')));
    $this->assertThresholdSettingsUIDefaults('test_sensor_outer', $thresholds);

    // Make sure that it is possible to save empty outer thresholds.
    $thresholds = array(
      'critical_low' => '',
      'warning_low' => '',
      'critical_high' => '',
      'warning_high' => '',
    );
    $this->submitThresholdSettings('test_sensor_outer', $thresholds);
    $this->assertText(SafeMarkup::format('Sensor @label saved.', array('@label' => 'Test sensor outer')));
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
