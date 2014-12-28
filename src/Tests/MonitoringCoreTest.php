<?php
/**
 * @file
 * Contains \Drupal\monitoring\Tests\MonitoringCoreTest.
 */
namespace Drupal\monitoring\Tests;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\monitoring\Entity\SensorConfig;
use Drupal\monitoring\SensorConfigInterface;

/**
 * Tests for the core pieces of monitoring.
 */
class MonitoringCoreTest extends MonitoringTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('dblog', 'image', 'node', 'taxonomy');

  public static function getInfo() {
    return array(
      'name' => 'Monitoring Drupal core',
      'description' => 'Drupal core sensors tests.',
      'group' => 'Monitoring',
    );
  }

  /**
   * Tests individual sensors.
   */
  public function testSensors() {

    // ======= CronLastRunAgeSensorPlugin tests ======= //

    $time_shift = (60 * 60 * 24 + 1);
    \Drupal::state()->set('system.cron_last', REQUEST_TIME - $time_shift);
    $result = $this->runSensor('core_cron_last_run_age');
    $this->assertTrue($result->isWarning());
    $this->assertEqual($result->getValue(), $time_shift);

    $time_shift = (60 * 60 * 24 * 3 + 1);
    \Drupal::state()->set('system.cron_last', REQUEST_TIME - $time_shift);
    $result = $this->runSensor('core_cron_last_run_age');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getValue(), $time_shift);

    \Drupal::service('cron')->run();

    $result = $this->runSensor('core_cron_last_run_age');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getValue(), 0);

    // ======= Cron safe threshold (poormanscron) tests ======= //

    $result = $this->runSensor('core_cron_safe_threshold');
    $this->assertTrue($result->isOk());
    \Drupal::config('system.cron')->set('threshold.autorun', 3600)->save();
    $result = $this->runSensor('core_cron_safe_threshold');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getMessage(), 'TRUE, expected FALSE');

    // ======= Maintenance mode tests ======= //

    $result = $this->runSensor('core_maintenance_mode');
    $this->assertTrue($result->isOk());
    \Drupal::state()->set('system.maintenance_mode', TRUE);
    $result = $this->runSensor('core_maintenance_mode');
    $this->assertTrue($result->isCritical());
    // Switch back to being online as being in maintenance mode would break
    // tests dealing with UI.
    \Drupal::state()->set('system.maintenance_mode', FALSE);
    $this->assertEqual($result->getMessage(), 'TRUE, expected FALSE');

    // ======= SensorQueue tests ======= //

    $sensor_config = SensorConfig::create(array(
      'id' => 'core_queue_monitoring_test',
      'plugin_id' => 'queue_size',
      'settings' => array(
        'queue' => 'monitoring_test'
      )
    ));
    $sensor_config->save();
    $queue = \Drupal::queue('monitoring_test');
    $queue->createItem(array());
    $queue->createItem(array());
    $result = $this->runSensor('core_queue_monitoring_test');
    $this->assertEqual($result->getValue(), 2);

    // ======= CoreRequirementsSensorPlugin tests ======= //

    // @todo - This should not be necessary after sensor requirements are updated.
    $sensor_config = SensorConfig::create(array(
      'id' => 'core_requirements_monitoring_test',
      'plugin_id' => 'core_requirements',
      'settings' => array(
        'module' => 'monitoring_test'
      )
    ));
    $sensor_config->save();
    $result = $this->runSensor('core_requirements_monitoring_test');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), 'Requirements check OK');

    // Set basic requirements saying that all is ok.
    $requirements = array(
      'requirement1' => array(
        'title' => 'requirement1',
        'description' => 'requirement1 description',
        'severity' => REQUIREMENT_OK,
      ),
      'requirement_excluded' => array(
        'title' => 'excluded requirement',
        'description' => 'requirement that should be excluded from monitoring by the sensor',
        // Set the severity to ERROR to test if the sensor result is not
        // affected by this requirement.
        'severity' => REQUIREMENT_ERROR,
      ),
    );
    \Drupal::state()->set('monitoring_test.requirements', $requirements);

    // Set requirements exclude keys into the sensor settings.
    $sensor_config = SensorConfig::load('core_requirements_monitoring_test');
    $settings = $sensor_config->getSettings();
    $settings['exclude keys'] = array('requirement_excluded');
    $sensor_config->settings = $settings;
    $sensor_config->save();

    // We still should have OK status but with different message.
    $result = $this->runSensor('core_requirements_monitoring_test');
    // We expect OK status as REQUIREMENT_ERROR is set by excluded requirement.
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), 'requirement1, requirement1 description');

    // Add warning state.
    $requirements['requirement2'] = array(
      'title' => 'requirement2',
      'description' => 'requirement2 description',
      'severity' => REQUIREMENT_WARNING,
    );
    \Drupal::state()->set('monitoring_test.requirements', $requirements);

    // Now the sensor should have escalated to the requirement in warning state.
    $result = $this->runSensor('core_requirements_monitoring_test');
    $this->assertTrue($result->isWarning());
    $this->assertEqual($result->getMessage(), 'requirement2, requirement2 description');

    // Add error state.
    $requirements['requirement3'] = array(
      'title' => 'requirement3',
      'description' => 'requirement3 description',
      'severity' => REQUIREMENT_ERROR,
    );
    \Drupal::state()->set('monitoring_test.requirements', $requirements);

    // Now the sensor should have escalated to the requirement in critical state.
    $result = $this->runSensor('core_requirements_monitoring_test');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getMessage(), 'requirement3, requirement3 description');

    // ======= Watchdog 404 in last 24 hours tests ======= //

    \Drupal::logger('page not found')->notice('not/found');
    $result = $this->runSensor('dblog_404');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), '1 watchdog events in 1 day, not/found');
    $this->assertEqual($result->getValue(), 1);

    for ($i = 1; $i <= 20; $i++) {
      \Drupal::logger('page not found')->notice('not/found');
    }

    $result = $this->runSensor('dblog_404');
    $this->assertEqual($result->getValue(), 21);
    $this->assertTrue($result->isWarning());

    for ($i = 0; $i <= 100; $i++) {
      \Drupal::logger('page not found')->notice('not/found/another');
    }

    $result = $this->runSensor('dblog_404');
    $this->assertEqual($result->getValue(), 101);
    $this->assertTrue($result->isCritical());

    // ======= ImageMissingStyleSensorPlugin tests ======= //

    $file = file_save_data($this->randomMachineName());
    /** @var FileUsageInterface $usage */
    $usage = \Drupal::service('file.usage');
    $usage->add($file, 'monitoring_test', 'test_object', 123456789);
    for ($i = 0; $i <= 5; $i++) {
      \Drupal::logger('image')->notice('Source image at %source_image_path not found while trying to generate derivative image at %derivative_path.',
        array(
          '%source_image_path' => $file->getFileUri(),
          '%derivative_path' => 'hash://styles/preview/1234.jpeg',
        ));
    }
    \Drupal::logger('image')->notice('Source image at %source_image_path not found while trying to generate derivative image at %derivative_path.',
      array(
        '%source_image_path' => 'public://portrait-pictures/bluemouse.jpeg',
        '%derivative_path' => 'hash://styles/preview/5678.jpeg',
      ));

    $result = $this->runSensor('dblog_image_missing_style');
    $this->assertEqual(6, $result->getValue());
    $this->assertTrue(strpos($result->getMessage(), $file->getFileUri()) !== FALSE);
    $this->assertTrue($result->isWarning());
    $this->assertTrue(strpos($result->getVerboseOutput(), 'monitoring_test') !== FALSE);
    $this->assertTrue(strpos($result->getVerboseOutput(), 'test_object') !== FALSE);
    $this->assertTrue(strpos($result->getVerboseOutput(), '123456789') !== FALSE);

    // ======= Watchdog sensor tests ======= //

    // Create watchdog entry with severity alert.
    // The testbot reported random fails with an unexpected watchdog record.
    // ALERT: "Missing filter plugin: %filter." with %filter = "filter_null"
    // Thus we drop all ALERT messages first.
    db_delete('watchdog')
      ->condition('severity', RfcLogLevel::ALERT)
      ->execute();
    \Drupal::logger('test')->alert('test message');

    // Run sensor and test the output.
    $severities = monitoring_event_severities();
    $result = $this->runSensor('dblog_event_severity_' . $severities[RfcLogLevel::ALERT]);
    $this->assertEqual($result->getValue(), 1);

    // ======= UserFailedLoginsSensorPlugin tests ======= //

    \Drupal::logger('user')->notice('Login attempt failed for %user.', array('%user' => 'user1'));
    \Drupal::logger('user')->notice('Login attempt failed for %user.', array('%user' => 'user1'));
    \Drupal::logger('user')->notice('Login attempt failed for %user.', array('%user' => 'user2'));

    $result = $this->runSensor('user_failed_logins');
    $this->assertEqual($result->getValue(), 3);
    $this->assertTrue(strpos($result->getMessage(), 'user1: 2') !== FALSE);
    $this->assertTrue(strpos($result->getMessage(), 'user2: 1') !== FALSE);

    // ======= SensorPlugin user_session_logouts tests ======= //

    \Drupal::logger('user')->notice('Session closed for %name.', array('%user' => 'user1'));
    \Drupal::logger('user')->notice('Session closed for %name.', array('%user' => 'user1'));
    \Drupal::logger('user')->notice('Session closed for %name.', array('%user' => 'user2'));

    $result = $this->runSensor('user_session_logouts');
    $this->assertEqual($result->getValue(), 3);
    $this->assertEqual($result->getMessage(), '3 logouts in 1 day');

    // ======= GitDirtyTreeSensorPlugin tests ======= //

    // Enable the sensor and set cmd to output something
    $sensor_config = SensorConfig::load('monitoring_git_dirty_tree');
    $sensor_config->status = TRUE;
    $sensor_config->settings['cmd'] = 'echo "dummy output\nanother dummy output"';
    $sensor_config->save();
    $result = monitoring_sensor_run('monitoring_git_dirty_tree', TRUE, TRUE);
    $this->assertTrue($result->isCritical());
    // The verbose output should contain the cmd output.
    $this->assertTrue(strpos($result->getVerboseOutput(), 'dummy output') !== FALSE);
    // Two lines of cmd output.
    $this->assertEqual($result->getValue(), 2);

    // Now echo empty string.
    $sensor_config->settings['cmd'] = 'echo ""';
    $sensor_config->save();

    $result = $this->runSensor('monitoring_git_dirty_tree');
    $this->assertTrue($result->isOk());
    // The message should say that it is ok.
    $this->assertTrue(strpos($result->getMessage(), 'Git repository clean') !== FALSE);

    // ======= Active sessions count tests ======= //
    // Create and login a user to have data in the sessions table.
    $test_user = $this->drupalCreateUser();
    $this->drupalLogin($test_user);
    $result = $this->runSensor('user_sessions_authenticated');
    $this->assertEqual($result->getValue(), 1);
    $result = $this->runSensor('user_sessions_all');
    $this->assertEqual($result->getValue(), 1);
    // Logout the user to see if sensors responded to the change.
    $this->drupalLogout();
    $result = $this->runSensor('user_sessions_authenticated');
    $this->assertEqual($result->getValue(), 0);
    $result = $this->runSensor('user_sessions_all');
    $this->assertEqual($result->getValue(), 0);

    // ======= node sensors tests ======= //

    $type1 = $this->drupalCreateContentType();
    $type2 = $this->drupalCreateContentType();
    $this->drupalCreateNode(array('type' => $type1->type));
    $this->drupalCreateNode(array('type' => $type1->type));
    $this->drupalCreateNode(array('type' => $type2->type));

    // Make sure that sensors for the new node types are available.
    monitoring_sensor_manager()->resetCache();
    $result = $this->runSensor('node_new_' . $type1->type);
    $this->assertEqual($result->getValue(), 2);
    // Test for the SensorSimpleDatabaseAggregator custom message.
    $this->assertEqual($result->getMessage(), String::format('@count @unit in @time_interval', array(
      '@count' => $result->getValue(),
      '@unit' => strtolower($result->getSensorConfig()->getValueLabel()),
      '@time_interval' => \Drupal::service('date.formatter')->formatInterval($result->getSensorConfig()
        ->getTimeIntervalValue()),
    )));

    $result = $this->runSensor('node_new_all');
    $this->assertEqual($result->getValue(), 3);

    \Drupal::config('system.theme')->set('default', 'bartik')->save();
    $result = $this->runSensor('core_theme_default');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), 'Value bartik');

    \Drupal::config('system.theme')->set('default', 'garland')->save();
    $result = $this->runSensor('core_theme_default');
    $this->assertTrue($result->isCritical());
  }

  /**
   * Tests for DisappearedSensorsSensorPlugin.
   *
   * We provide a separate test method for the DisappearedSensorsSensorPlugin as we
   * need to enable and disable additional modules.
   */
  public function testSensorDisappearedSensors() {

    $module_handler = \Drupal::service('module_installer');

    // Install the comment module and the comment_new sensor.
    $module_handler->install(array('comment'));
    monitoring_sensor_manager()->enableSensor('comment_new');

    // Run the disappeared sensor - it should not report any problems.
    $result = $this->runSensor('monitoring_disappeared_sensors');
    $this->assertTrue($result->isOk());

    $log = $this->loadWatchdog();
    $this->assertEqual(count($log), 2, 'There should be two log entries: comment_new sensor added, all sensors enabled by default added.');
    $this->assertEqual(String::format($log[0]->message, unserialize($log[0]->variables)),
      String::format('@count new sensor/s added: @names', array('@count' => 1, '@names' => 'comment_new')));

    $sensor_config_all = monitoring_sensor_manager()->getAllSensorConfig();
    unset($sensor_config_all['comment_new']);
    $this->assertEqual(String::format($log[1]->message, unserialize($log[1]->variables)),
      String::format('@count new sensor/s added: @names', array(
        '@count' => count($sensor_config_all),
        '@names' => implode(', ', array_keys($sensor_config_all))
      )));

    // Uninstall the comment module so that the comment_new sensor goes away.
    $module_handler->uninstall(array('comment'));

    // The comment_new sensor has gone away and therefore we should have the
    // critical status.
    $result = $this->runSensor('monitoring_disappeared_sensors');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getMessage(), 'Missing sensor comment_new');
    // There should be no new logs.
    $this->assertEqual(count($this->loadWatchdog()), 2);

    // Install the comment module to test the correct procedure of removing
    // sensors.
    $module_handler->install(array('comment'));
    monitoring_sensor_manager()->enableSensor('comment_new');

    // Now we should be back to normal.
    $result = $this->runSensor('monitoring_disappeared_sensors');
    $this->assertTrue($result->isOk());
    $this->assertEqual(count($this->loadWatchdog()), 2);

    // Do the correct procedure to remove a sensor - first disable the sensor
    // and then uninstall the comment module.
    monitoring_sensor_manager()->disableSensor('comment_new');
    $module_handler->uninstall(array('comment'));

    // The sensor should not report any problem this time.
    $result = $this->runSensor('monitoring_disappeared_sensors');
    $this->assertTrue($result->isOk());
    $log = $this->loadWatchdog();
    $this->assertEqual(count($log), 3, 'Removal of comment_new sensor should be logged.');
    $this->assertEqual(String::format($log[2]->message, unserialize($log[2]->variables)),
      String::format('@count new sensor/s removed: @names', array('@count' => 1, '@names' => 'comment_new')));

    // === Test the UI === //
    $account = $this->drupalCreateUser(array('administer monitoring'));
    $this->drupalLogin($account);
    // Install comment module and the comment_new sensor.
    $module_handler->install(array('comment'));
    monitoring_sensor_manager()->enableSensor('comment_new');

    // We should have the message that no sensors are missing.
    $this->drupalGet('admin/config/system/monitoring/sensors/monitoring_disappeared_sensors');
    $this->assertNoText(t('This action will clear the missing sensors and the critical sensor status will go away.'));

    // Disable sensor and the comment module. This is the correct procedure and
    // therefore there should be no missing sensors.
    monitoring_sensor_manager()->disableSensor('comment_new');
    $this->drupalGet('admin/config/system/monitoring/sensors/monitoring_disappeared_sensors');
    $this->assertNoText(t('This action will clear the missing sensors and the critical sensor status will go away.'));

    // Install comment module and the comment_new sensor.
    $module_handler->install(array('comment'));
    monitoring_sensor_manager()->enableSensor('comment_new');
    // Now disable the comment module to have the comment_new sensor disappear.
    $module_handler->uninstall(array('comment'));
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
   * Tests the UI/settings of the enabled modules sensor.
   */
  public function testSensorInstalledModulesUI() {
    $account = $this->drupalCreateUser(array('administer monitoring'));
    $this->drupalLogin($account);

    // Visit settings page of the disabled sensor. We run the sensor to check
    // for deltas. This led to fatal errors with a disabled sensor.
    $this->drupalGet('admin/config/system/monitoring/sensors/monitoring_enabled_modules');

    // Enable the sensor.
    monitoring_sensor_manager()->enableSensor('monitoring_enabled_modules');

    // Test submitting the defaults and enabling the sensor.
    $this->drupalPostForm('admin/config/system/monitoring/sensors/monitoring_enabled_modules', array(
      'status' => TRUE,
    ), t('Save'));
    // Reset the sensor config so that it reflects changes done via POST.
    monitoring_sensor_manager()->resetCache();
    // The sensor should now be OK.
    $result = $this->runSensor('monitoring_enabled_modules');
    $this->assertTrue($result->isOk());

    // Expect the contact and book modules to be installed.
    $this->drupalPostForm('admin/config/system/monitoring/sensors/monitoring_enabled_modules', array(
      'settings[modules][contact]' => TRUE,
      'settings[modules][book]' => TRUE,
    ), t('Save'));
    // Reset the sensor config so that it reflects changes done via POST.
    monitoring_sensor_manager()->resetCache();
    // Make sure the extended / hidden_modules form submit cleanup worked and
    // they are not stored as a duplicate in settings.
    $sensor_config = SensorConfig::load('monitoring_enabled_modules');
    $this->assertTrue(!array_key_exists('extended', $sensor_config->settings), 'Do not persist extended module hidden selections separately.');
    // The sensor should escalate to CRITICAL.
    $result = $this->runSensor('monitoring_enabled_modules');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getMessage(), '2 modules delta, expected 0, Following modules are expected to be installed: Book (book), Contact (contact)');
    $this->assertEqual($result->getValue(), 2);

    // Reset modules selection with the update selection (ajax) button.
    $this->drupalGet('admin/config/system/monitoring/sensors/monitoring_enabled_modules');
    $this->drupalPostAjaxForm(NULL, array(), array('op' => t('Update module selection')));
    $this->drupalPostForm(NULL, array(), t('Save'));
    $result = $this->runSensor('monitoring_enabled_modules');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), '0 modules delta');

    // The default setting is not to allow additional modules. Enable comment
    // and the sensor should escalate to CRITICAL.
    \Drupal::service('module_installer')->install(array('help'));
    // The container is rebuilt and needs to be reassigned to avoid static
    // config cache issues. See https://www.drupal.org/node/2398867
    $this->container = \Drupal::getContainer();
    $result = $this->runSensor('monitoring_enabled_modules');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getMessage(), '1 modules delta, expected 0, Following modules are NOT expected to be installed: Help (help)');
    $this->assertEqual($result->getValue(), 1);
    // Allow additional, the sensor should not escalate anymore.
    $this->drupalPostForm('admin/config/system/monitoring/sensors/monitoring_enabled_modules', array(
      'settings[allow_additional]' => 1,
    ), t('Save'));
    $result = $this->runSensor('monitoring_enabled_modules');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), '0 modules delta');
  }

  /**
   * Test cases for EnabledModulesSensorPlugin sensor.
   *
   * We use separate test method as we need to enable/disable modules.
   */
  public function testSensorInstalledModulesAPI() {
    // The initial run of the sensor will acknowledge all installed modules as
    // expected and so the status should be OK.
    $result = $this->runSensor('monitoring_enabled_modules');
    $this->assertTrue($result->isOk());

    // Install additional module. As the setting "allow_additional" is not
    // enabled by default this should result in sensor escalation to critical.
    \Drupal::service('module_installer')->install(array('contact'));
    $result = $this->runSensor('monitoring_enabled_modules');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getMessage(), '1 modules delta, expected 0, Following modules are NOT expected to be installed: Contact (contact)');
    $this->assertEqual($result->getValue(), 1);

    // Allow additional modules and run the sensor - it should not escalate now.
    $sensor_config = SensorConfig::load('monitoring_enabled_modules');
    $sensor_config->settings['allow_additional'] = TRUE;
    $sensor_config->save();
    $result = $this->runSensor('monitoring_enabled_modules');
    $this->assertTrue($result->isOk());

    // Add comment module to be expected and disable the module. The sensor
    // should escalate to critical.
    $sensor_config->settings['modules']['contact'] = 'contact';
    $sensor_config->save();
    \Drupal::service('module_installer')->uninstall(array('contact'));
    $result = $this->runSensor('monitoring_enabled_modules');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getMessage(), '1 modules delta, expected 0, Following modules are expected to be installed: Contact (contact)');
    $this->assertEqual($result->getValue(), 1);
  }

  /**
   * Tests the watchdog entries aggregator.
   */
  public function testGenericDBAggregate() {

    // Aggregate by watchdog type.
    $sensor_config = SensorConfig::load('watchdog_aggregate_test');
    $sensor_config->settings['conditions'] = array(
      array('field' => 'type', 'value' => 'test_type'),
    );
    $sensor_config->save();
    \Drupal::logger('test_type')->notice($this->randomMachineName());
    \Drupal::logger('test_type')->notice($this->randomMachineName());
    \Drupal::logger('other_test_type')->notice($this->randomMachineName());
    $result = $this->runSensor('watchdog_aggregate_test');
    $this->assertEqual($result->getValue(), 2);

    // Aggregate by watchdog message.
    $sensor_config->settings['conditions'] = array(
      array('field' => 'message', 'value' => 'test_message'),
    );
    $sensor_config->save();
    \Drupal::logger($this->randomMachineName())->notice('test_message');
    \Drupal::logger($this->randomMachineName())->notice('another_test_message');
    \Drupal::logger($this->randomMachineName())->notice('another_test_message');
    $result = $this->runSensor('watchdog_aggregate_test');
    $this->assertEqual($result->getValue(), 1);

    // Aggregate by watchdog severity.
    $sensor_config->settings['conditions'] = array(
      array('field' => 'severity', 'value' => RfcLogLevel::CRITICAL),
    );
    $sensor_config->save();
    \Drupal::logger($this->randomMachineName())->critical($this->randomMachineName());
    \Drupal::logger($this->randomMachineName())->critical($this->randomMachineName());
    \Drupal::logger($this->randomMachineName())->critical($this->randomMachineName());
    \Drupal::logger($this->randomMachineName())->critical($this->randomMachineName());
    $result = $this->runSensor('watchdog_aggregate_test');
    $this->assertEqual($result->getValue(), 4);

    // Aggregate by watchdog location.
    $sensor_config->settings['conditions'] = array(
      array('field' => 'location', 'value' => 'http://some.url.dev'),
    );
    $sensor_config->save();
    // Update the two test_type watchdog entries with a custom location.
    db_update('watchdog')
      ->fields(array('location' => 'http://some.url.dev'))
      ->condition('type', 'test_type')
      ->execute();
    $result = $this->runSensor('watchdog_aggregate_test');
    $this->assertEqual($result->getValue(), 2);

    // Filter for time period.
    $sensor_config->settings['conditions'] = array();
    $sensor_config->settings['time_interval_value'] = 10;
    $sensor_config->settings['time_interval_field'] = 'timestamp';
    $sensor_config->save();

    // Make all system watchdog messages older than the configured time period.
    db_update('watchdog')
      ->fields(array('timestamp' => REQUEST_TIME - 20))
      ->condition('type', 'system')
      ->execute();
    $count_latest = db_query('SELECT COUNT(*) FROM {watchdog} WHERE timestamp > :timestamp', array(':timestamp' => REQUEST_TIME - 10))->fetchField();
    $result = $this->runSensor('watchdog_aggregate_test');
    $this->assertEqual($result->getValue(), $count_latest);

    // Test for thresholds and statuses.
    $sensor_config->settings['conditions'] = array(
      array('field' => 'type', 'value' => 'test_watchdog_aggregate_sensor'),
    );
    $sensor_config->save();
    $result = $this->runSensor('watchdog_aggregate_test');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getValue(), 0);

    \Drupal::logger('test_watchdog_aggregate_sensor')->notice('testing');
    \Drupal::logger('test_watchdog_aggregate_sensor')->notice('testing');
    $result = $this->runSensor('watchdog_aggregate_test');
    $this->assertTrue($result->isWarning());
    $this->assertEqual($result->getValue(), 2);

    \Drupal::logger('test_watchdog_aggregate_sensor')->notice('testing');
    $result = $this->runSensor('watchdog_aggregate_test');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getValue(), 3);

    // Test with different db table.
    $type1 = $this->drupalCreateContentType();
    $type2 = $this->drupalCreateContentType();
    $sensor_config = SensorConfig::load('db_aggregate_test');
    $this->drupalCreateNode(array('type' => $type1->type));
    $this->drupalCreateNode(array('type' => $type2->type));
    $this->drupalCreateNode(array('type' => $type2->type));
    // Create one node that should not meet the time_interval condition.
    $node = $this->drupalCreateNode(array('type' => $type2->type));
    db_update('node_field_data')
      ->fields(array('created' => REQUEST_TIME - ($sensor_config->getTimeIntervalValue() + 10)))
      ->condition('nid', $node->id())
      ->execute();

    // Test for the node type1.
    $sensor_config = SensorConfig::load('db_aggregate_test');
    $sensor_config->settings['conditions'] = array(
      'test' => array('field' => 'type', 'value' => $type1->type),
    );
    $sensor_config->save();
    $result = $this->runSensor('db_aggregate_test');
    $this->assertEqual($result->getValue(), '1');

    // Test for node type2.
    $sensor_config->settings['conditions'] = array(
      'test' => array('field' => 'type', 'value' => $type2->type),
    );
    $sensor_config->save();
    $result = $this->runSensor('db_aggregate_test');
    // There should be two nodes with node type2 and created in last 24 hours.
    $this->assertEqual($result->getValue(), 2);

    // Test support for configurable fields, create a taxonomy reference field.
    $vocabulary = $this->createVocabulary();

    entity_create('field_storage_config', array(
      'field_name' => 'term_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'entity_type' => 'node',
      'type' => 'taxonomy_term_reference',
      'entity_types' => array('node'),
      'settings' => array(
        'allowed_values' => array(
          array(
            'vocabulary' => $vocabulary->id(),
            'parent' => 0,
          ),
        ),
      ),
    ))->save();

    entity_create('field_config', array(
      'label' => 'Term reference',
      'field_name' => 'term_reference',
      'entity_type' => 'node',
      'bundle' => $type2->type,
      'settings' => array(),
      'required' => FALSE,
      'widget' => array(
        'type' => 'options_select',
      ),
      'display' => array(
        'default' => array(
          'type' => 'taxonomy_term_reference_link',
        ),
      ),
    ))->save();

    entity_create('field_storage_config', array(
      'field_name' => 'term_reference2',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'entity_type' => 'node',
      'type' => 'taxonomy_term_reference',
      'entity_types' => array('node'),
      'settings' => array(
        'allowed_values' => array(
          array(
            'vocabulary' => $vocabulary->id(),
            'parent' => 0,
          ),
        ),
      ),
    ))->save();

    entity_create('field_config', array(
      'label' => 'Term reference 2',
      'field_name' => 'term_reference2',
      'entity_type' => 'node',
      'bundle' => $type2->type,
      'settings' => array(),
      'required' => FALSE,
      'widget' => array(
        'type' => 'options_select',
      ),
      'display' => array(
        'default' => array(
          'type' => 'taxonomy_term_reference_link',
        ),
      ),
    ))->save();

    // Create some terms.
    $term1 = $this->createTerm($vocabulary);
    $term2 = $this->createTerm($vocabulary);

    // Create node that only references the first term.
    $this->drupalCreateNode(array(
      'created' => REQUEST_TIME,
      'type' => $type2->type,
      'term_reference' => array(array('target_id' => $term1->id())),
    ));

    // Create node that only references both terms.
    $this->drupalCreateNode(array(
      'created' => REQUEST_TIME,
      'type' => $type2->type,
      'term_reference' => array(
        array('target_id' => $term1->id()),
        array('target_id' => $term2->id()),
      ),
    ));

    // Create a third node that references both terms but in different fields.
    $this->drupalCreateNode(array(
      'created' => REQUEST_TIME,
      'type' => $type2->type,
      'term_reference' => array(array('target_id' => $term1->id())),
      'term_reference2' => array(array('target_id' => $term2->id())),
    ));

    // Update the sensor to look for nodes with a reference to term1 in the
    // first field.
    $sensor_config->settings['conditions'] = array(
      'test' => array('field' => 'term_reference.target_id', 'value' => $term1->id()),
    );
    $sensor_config->settings['entity_type'] = 'node';
    $sensor_config->save();
    $result = $this->runSensor('db_aggregate_test');
    // There should be three nodes with that reference.
    $this->assertEqual($result->getValue(), 3);

    // Update the sensor to look for nodes with a reference to term1 in the
    // first field and term2 in the second.
    $sensor_config->settings['conditions'] = array(
      'test' => array('field' => 'term_reference.target_id', 'value' => $term1->id()),
      'test2' => array(
        'field' => 'term_reference2.target_id',
        'value' => $term2->id(),
      ),
    );
    $sensor_config->save();
    $result = $this->runSensor('db_aggregate_test');
    // There should be one nodes with those references.
    $this->assertEqual($result->getValue(), 1);
  }

  /**
   * Returns a new vocabulary with random properties.
   *
   * @return \Drupal\taxonomy\VocabularyInterface;
   *   Vocabulary object.
   */
  protected function createVocabulary() {
    // Create a vocabulary.
    $vocabulary = entity_create('taxonomy_vocabulary');
    $vocabulary->vid = Unicode::strtolower($this->randomMachineName());
    $vocabulary->name = $this->randomMachineName();
    $vocabulary->description = $this->randomMachineName();
    $vocabulary->save();
    return $vocabulary;
  }

  /**
   * Returns a new term with random properties in vocabulary $vid.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $vocabulary
   *   The vocabulary where the term will belong to.
   *
   * @return \Drupal\taxonomy\TermInterface;
   *   Term object.
   */
  protected function createTerm($vocabulary) {
    $term = entity_create('taxonomy_term', array('vid' => $vocabulary->id()));
    $term->name = $this->randomMachineName();
    $term->description = $this->randomMachineName();
    $term->save();
    return $term;
  }

  /**
   * Loads watchdog entries by type.
   *
   * @param string $type
   *   Watchdog type.
   *
   * @return array
   *   List of dblog entries.
   */
  protected function loadWatchdog($type = 'monitoring') {
    return db_query("SELECT * FROM {watchdog} WHERE type = :type", array(':type' => $type))
      ->fetchAll();
  }

}
