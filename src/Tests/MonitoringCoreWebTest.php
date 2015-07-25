<?php
/**
 * @file
 * Contains \Drupal\monitoring\Tests\MonitoringCoreWebTest.
 */
namespace Drupal\monitoring\Tests;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\monitoring\Entity\SensorConfig;

/**
 * Integration tests for the core pieces of monitoring.
 *
 * @group monitoring
 */
class MonitoringCoreWebTest extends MonitoringTestBase {

  public static $modules = array('dblog', 'image', 'node', 'taxonomy');

  /**
   * Tests individual sensors.
   */
  public function testSensors() {
    $this->doTestUserIntegritySensorPlugin();
    $this->doTestDatabaseAggregatorSensorPluginActiveSessions();
    $this->doTestTwigDebugSensor();
    $this->doTestWatchdogAggregatorSensorPlugin();
  }

  /**
   * Tests successful user logins through watchdog sensor.
   *
   * @see DatabaseAggregatorSensorPlugin
   */
  protected function doTestWatchdogAggregatorSensorPlugin() {
    // Create and login user with permission to edit sensors and view reports.
    $test_user = $this->drupalCreateUser([
      'administer site configuration',
      'administer monitoring',
      'monitoring reports',
      'access site reports',
      'monitoring verbose',
    ]);
    $this->drupalLogin($test_user);
    // Test output and default message replacement.
    $this->drupalGet('admin/reports/monitoring/sensors/user_successful_logins');
    $xpath = $this->xpath('//fieldset[@id="edit-verbose"]/div[@class="fieldset-wrapper"]/table/tbody/tr[@class="entity odd"]');
    $wid = (string) $xpath[0]->td[0];
    $message = (string) $xpath[0]->td[1];
    $this->assertTrue($wid == 6, 'Found WID in verbose output (WID == 6)');
    $this->assertTrue($message == 'Session opened for .', 'Found replaced message in output.');
    $this->assertText('Session opened for ' . $test_user->label());
    // Remove variables from the fields and assert message has no replacements.
    $this->drupalPostForm('admin/config/system/monitoring/sensors/user_successful_logins', ['keys' => 'wid' . PHP_EOL . 'message'], t('Save'));
    $this->drupalGet('admin/reports/monitoring/sensors/user_successful_logins');
    $xpath = $this->xpath('//fieldset[@id="edit-verbose"]/div[@class="fieldset-wrapper"]/table/tbody/tr[@class="entity odd"]');
    $wid = (string) $xpath[0]->td[0];
    $message = (string) $xpath[0]->td[1];
    $this->assertTrue($wid == 6, 'Found WID in verbose output (WID == 6)');
    $this->assertTrue($message == 'Session opened for %name.', 'Found unreplaced message in output.');
    // Test wrong configuration (messages field does not exist).
    $this->drupalPostForm('admin/config/system/monitoring/sensors/user_successful_logins', ['keys' => 'wid' . PHP_EOL . 'messages'], t('Save'));
    $this->assertText('Verbose output configuration is invalid, keys were not saved.');
  }

  /**
   * Tests active session count through db aggregator sensor.
   *
   * @see DatabaseAggregatorSensorPlugin
   */
  protected function doTestDatabaseAggregatorSensorPluginActiveSessions() {
    // Create and login a user to have data in the sessions table.
    $test_user = $this->drupalCreateUser([
      'monitoring reports',
      'access site reports',
      'monitoring verbose',
    ]);
    $this->drupalLogin($test_user);
    $test_user_name = $test_user->label();

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

    // Check verbose output.
    $this->drupalLogin($test_user);
    $this->drupalGet('/admin/reports/monitoring/sensors/user_sessions_authenticated');
    // 3 fields are expected to be displayed.
    $results = $this->xpath('//fieldset[@id="edit-verbose"]//table//tbody//tr')[0]->td;
    $this->assertTrue(count($results) == 3, '3 fields have been found in the verbose result.');
    // The username should be replaced in the message.
    $this->drupalGet('/admin/reports/monitoring/sensors/dblog_event_severity_notice');
    $this->assertText('Session opened for ' . $test_user->label());
    // 'No results' text is displayed when the query has 0 results.
    $this->drupalGet('/admin/reports/monitoring/sensors/dblog_event_severity_warning');
    $this->assertText('No results were found in the table.');
  }

  /**
   * Tests the twig debug sensor.
   *
   * @see \Drupal\monitoring\Plugin\monitoring\SensorPlugin\TwigDebugSensorPlugin
   */
  public function doTestTwigDebugSensor() {

    // Ensure that the sensor does not report an error with the default
    // configuration.
    $result = $this->runSensor('twig_debug_mode');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), 'Optimal configuration');

    $twig_config = $this->container->getParameter('twig.config');
    // Set parameters to the optimal configuration to make sure implicit changes
    // does not trigger any notices and check sensor message.
    $twig_config['debug'] = FALSE;
    $twig_config['cache'] = TRUE;
    $twig_config['auto_reload'] = NULL;
    $this->setContainerParameter('twig.config', $twig_config);
    $this->rebuildContainer();

    $result = $this->runSensor('twig_debug_mode');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), 'Optimal configuration');

    $twig_config = $this->container->getParameter('twig.config');
    // Change parameters and check sensor message.
    $twig_config['debug'] = TRUE;
    $twig_config['cache'] = FALSE;
    $twig_config['auto_reload'] = TRUE;
    $this->setContainerParameter('twig.config', $twig_config);
    $this->rebuildContainer();

    $result = $this->runSensor('twig_debug_mode');
    $this->assertTrue($result->isWarning());
    $this->assertEqual($result->getMessage(), 'Twig debug mode is enabled, Twig cache disabled, Automatic recompilation of Twig templates enabled');
  }

  /**
   * Tests the user integrity sensor.
   *
   * @see UserIntegritySensorPlugin
   */
  protected function doTestUserIntegritySensorPlugin() {
    $test_user_first = $this->drupalCreateUser(array('administer monitoring'), 'test_user_1');
    $this->drupalLogin($test_user_first);
    // Check sensor message after first privilege user creation.
    $result = $this->runSensor('user_integrity');
    $this->assertEqual($result->getMessage(), '1 privileged user(s)');

    // Create second privileged user.
    $test_user_second = $this->drupalCreateUser(array(), 'test_user_2', TRUE);
    $this->drupalLogin($test_user_second);
    // Check sensor message after new privilege user creation.
    $result = $this->runSensor('user_integrity');
    $this->assertEqual($result->getMessage(), '2 privileged user(s), 1 new user(s)');

    // Reset the user data, button is tested in UI tests.
    \Drupal::keyValue('monitoring.users')->deleteAll();
    $result = $this->runSensor('user_integrity');
    $this->assertEqual($result->getMessage(), '2 privileged user(s)');

    // Make changes to a user.
    $test_user_second->setUsername('changed');
    $test_user_second->save();
    // Check sensor message for user changes.
    $result = $this->runSensor('user_integrity');
    $this->assertEqual($result->getMessage(), '2 privileged user(s), 1 changed user(s)');

    // Reset the user data again, check sensor message.
    \Drupal::keyValue('monitoring.users')->deleteAll();
    $result = $this->runSensor('user_integrity');
    $this->assertEqual($result->getMessage(), '2 privileged user(s)');
  }

  /**
   * Tests for disappearing sensors.
   *
   * We provide a separate test method for the DisappearedSensorsSensorPlugin as we
   * need to install and uninstall additional modules.
   *
   * @see DisappearedSensorsSensorPlugin
   */
  public function testSensorDisappearedSensors() {
    // Install the comment module.
    $this->installModules(array('comment'));

    // Run the disappeared sensor - it should not report any problems.
    $result = $this->runSensor('monitoring_disappeared_sensors');
    $this->assertTrue($result->isOk());

    $log = $this->loadWatchdog();
    $this->assertEqual(count($log), 1, 'There should be one log entry: all sensors enabled by default added.');

    $sensor_config_all = monitoring_sensor_manager()->getAllSensorConfig();
    $this->assertEqual(SafeMarkup::format($log[0]->message, unserialize($log[0]->variables)),
      SafeMarkup::format('@count new sensor/s added: @names', array(
        '@count' => count($sensor_config_all),
        '@names' => implode(', ', array_keys($sensor_config_all))
      )));

    // Uninstall the comment module so that the comment_new sensor goes away.
    $this->uninstallModules(array('comment'));

    // The comment_new sensor has gone away and therefore we should have the
    // critical status.
    $result = $this->runSensor('monitoring_disappeared_sensors');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getMessage(), 'Missing sensor comment_new');
    // There should be no new logs.
    $this->assertEqual(count($this->loadWatchdog()), 1);

    // Install the comment module to test the correct procedure of removing
    // sensors.
    $this->installModules(array('comment'));
    monitoring_sensor_manager()->enableSensor('comment_new');

    // Now we should be back to normal.
    $result = $this->runSensor('monitoring_disappeared_sensors');
    $this->assertTrue($result->isOk());
    $this->assertEqual(count($this->loadWatchdog()), 1);

    // Do the correct procedure to remove a sensor - first disable the sensor
    // and then uninstall the comment module.
    monitoring_sensor_manager()->disableSensor('comment_new');
    $this->uninstallModules(array('comment'));

    // The sensor should not report any problem this time.
    $result = $this->runSensor('monitoring_disappeared_sensors');
    $this->assertTrue($result->isOk());
    $log = $this->loadWatchdog();
    $this->assertEqual(count($log), 2, 'Removal of comment_new sensor should be logged.');
    $this->assertEqual(SafeMarkup::format($log[1]->message, unserialize($log[1]->variables)),
      SafeMarkup::format('@count new sensor/s removed: @names', array(
          '@count' => 1,
          '@names' => 'comment_new'
        )));
  }

  /**
   * Tests enabled modules sensor.
   *
   * We use separate test method as we need to install/uninstall modules.
   *
   * @see EnabledModulesSensorPlugin
   */
  public function testSensorInstalledModulesAPI() {
    // The initial run of the sensor will acknowledge all installed modules as
    // expected and so the status should be OK.
    $result = $this->runSensor('monitoring_installed_modules');
    $this->assertTrue($result->isOk());

    // Install additional module. As the setting "allow_additional" is not
    // enabled by default this should result in sensor escalation to critical.
    $this->installModules(array('contact'));
    $result = $this->runSensor('monitoring_installed_modules');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getMessage(), '1 modules delta, expected 0, Following modules are NOT expected to be installed: Contact (contact)');
    $this->assertEqual($result->getValue(), 1);

    // Allow additional modules and run the sensor - it should not escalate now.
    $sensor_config = SensorConfig::load('monitoring_installed_modules');
    $sensor_config->settings['allow_additional'] = TRUE;
    $sensor_config->save();
    $result = $this->runSensor('monitoring_installed_modules');
    $this->assertTrue($result->isOk());

    // Install comment module to be expected and uninstall the module again.
    // The sensor should escalate to critical.
    $sensor_config->settings['modules']['contact'] = 'contact';
    $sensor_config->save();
    $this->uninstallModules(array('contact'));
    $result = $this->runSensor('monitoring_installed_modules');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getMessage(), '1 modules delta, expected 0, Following modules are expected to be installed: Contact (contact)');
    $this->assertEqual($result->getValue(), 1);
  }


  /**
   * Tests the entity aggregator.
   *
   * @see ContentEntityAggregatorSensorPlugin
   */
  public function testEntityAggregator() {
    // Create content types and nodes.
    $type1 = $this->drupalCreateContentType();
    $type2 = $this->drupalCreateContentType();
    $sensor_config = SensorConfig::load('entity_aggregate_test');
    $node1 = $this->drupalCreateNode(array('type' => $type1->id()));
    $node2 = $this->drupalCreateNode(array('type' => $type2->id()));
    $this->drupalCreateNode(array('type' => $type2->id()));
    // One node should not meet the time_interval condition.
    $node = $this->drupalCreateNode(array('type' => $type2->id()));
    db_update('node_field_data')
      ->fields(array('created' => REQUEST_TIME - ($sensor_config->getTimeIntervalValue() + 10)))
      ->condition('nid', $node->id())
      ->execute();

    // Test for the node type1.
    $sensor_config = SensorConfig::load('entity_aggregate_test');
    $sensor_config->settings['conditions'] = array(
      'test' => array('field' => 'type', 'value' => $type1->id()),
    );
    $sensor_config->save();
    $result = $this->runSensor('entity_aggregate_test');
    $this->assertEqual($result->getValue(), '1');

    // Test for node type2.
    $sensor_config->settings['conditions'] = array(
      'test' => array('field' => 'type', 'value' => $type2->id()),
    );
    $sensor_config->save();
    $result = $this->runSensor('entity_aggregate_test');
    // There should be two nodes with node type2 and created in last 24 hours.
    $this->assertEqual($result->getValue(), 2);

    // Test support for configurable fields, create a taxonomy reference field.
    $vocabulary = $this->createVocabulary();

    entity_create('field_storage_config', array(
      'field_name' => 'term_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'entity_types' => array('node'),
      'settings' => array(
        'target_type' => 'taxonomy_term',
      ),
    ))->save();

    entity_create('field_config', array(
      'label' => 'Term reference',
      'field_name' => 'term_reference',
      'entity_type' => 'node',
      'bundle' => $type2->id(),
      'settings' => array('bundles' => [$vocabulary->id() => $vocabulary->id()]),
      'required' => FALSE,
    ))->save();

    entity_create('field_storage_config', array(
      'field_name' => 'term_reference2',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'entity_types' => array('node'),
      'settings' => array(
        'target_type' => 'taxonomy_term',
      ),
    ))->save();

    entity_create('field_config', array(
      'label' => 'Term reference 2',
      'field_name' => 'term_reference2',
      'entity_type' => 'node',
      'bundle' => $type2->id(),
      'settings' => array('bundles' => [$vocabulary->id() => $vocabulary->id()]),
      'required' => FALSE,
    ))->save();

    // Create some terms.
    $term1 = $this->createTerm($vocabulary);
    $term2 = $this->createTerm($vocabulary);

    // Create node that only references the first term.
    $node1 = $this->drupalCreateNode(array(
      'created' => REQUEST_TIME,
      'type' => $type2->id(),
      'term_reference' => array(array('target_id' => $term1->id())),
    ));

    // Create node that only references both terms.
    $node2 = $this->drupalCreateNode(array(
      'created' => REQUEST_TIME,
      'type' => $type2->id(),
      'term_reference' => array(
        array('target_id' => $term1->id()),
        array('target_id' => $term2->id()),
      ),
    ));

    // Create a third node that references both terms but in different fields.
    $node3 = $this->drupalCreateNode(array(
      'created' => REQUEST_TIME,
      'type' => $type2->id(),
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
    $result = $this->runSensor('entity_aggregate_test');
    // There should be three nodes with that reference.
    $this->assertEqual($result->getValue(), 3);

    // Check the content entity aggregator verbose output and other UI elements.
    $this->drupalLogin($this->createUser(['monitoring reports', 'administer monitoring']));
    $this->drupalPostForm('admin/reports/monitoring/sensors/entity_aggregate_test', [], t('Run now'));
    $this->assertText('id');
    $this->assertText('label');
    $this->assertLink($node1->label());
    $this->assertLink($node2->label());
    $this->assertLink($node3->label());
    $this->clickLink(t('Edit'));
    // Assert some of the 'available fields'.
    $this->assertText('Content: nid, uuid, vid, type, langcode, title, uid, status, created, changed, promote, sticky, revision_timestamp, revision_uid,');
    $this->assertFieldByName('conditions[0][field]', 'term_reference.target_id');
    $this->assertFieldByName('conditions[0][value]', $term1->id());

    // Test adding another field.
    $this->drupalPostForm(NULL, [
      'settings[verbose_fields][2]' => 'revision_timestamp',
    ] , t('Add another field'));
    // Repeat for a condition, add an invalid field while we are at it.
    $this->drupalPostForm(NULL, [
    'conditions[1][field]' => 'nid',
      'conditions[1][operator]' => '>',
      'conditions[1][value]' => 4,
      // The invalid field.
      'settings[verbose_fields][3]' => 'test_wrong_field',
    ] , t('Add another condition'));

    $this->drupalPostForm(NULL, [], t('Save'));
    $this->clickLink('Entity Aggregate test');

    // Assert the new field and it's formatted output.
    $this->assertText('revision_timestamp');
    $this->assertText(\Drupal::service('date.formatter')->format($node1->getRevisionCreationTime(), 'short'));
    $this->assertText(\Drupal::service('date.formatter')->format($node2->getRevisionCreationTime(), 'short'));
    $this->assertText(\Drupal::service('date.formatter')->format($node3->getRevisionCreationTime(), 'short'));

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
    $result = $this->runSensor('entity_aggregate_test');
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
    $vocabulary = entity_create('taxonomy_vocabulary', array(
      'vid' => Unicode::strtolower($this->randomMachineName()),
      'name' => $this->randomMachineName(),
      'description' => $this->randomMachineName(),
    ));
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
