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
    $this->doTestDatabaseAggregatorSensorPluginActiveSessions();
    $this->doTestTwigDebugSensor();
    $this->doTestUserIntegritySensorPlugin();
  }

  /**
   * Tests active session count through db aggregator sensor.
   *
   * @see DatabaseAggregatorSensorPlugin
   */
  protected function doTestDatabaseAggregatorSensorPluginActiveSessions() {
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
    $test_user_second = $this->drupalCreateUser(array('administer monitoring'), 'test_user_2');
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
   * need to enable and disable additional modules.
   *
   * @see DisappearedSensorsSensorPlugin
   */
  public function testSensorDisappearedSensors() {
    // Install the comment module and the comment_new sensor.
    $this->installModules(array('comment'));
    monitoring_sensor_manager()->enableSensor('comment_new');

    // Run the disappeared sensor - it should not report any problems.
    $result = $this->runSensor('monitoring_disappeared_sensors');
    $this->assertTrue($result->isOk());

    $log = $this->loadWatchdog();
    $this->assertEqual(count($log), 2, 'There should be two log entries: comment_new sensor added, all sensors enabled by default added.');
    $this->assertEqual(SafeMarkup::format($log[0]->message, unserialize($log[0]->variables)),
      SafeMarkup::format('@count new sensor/s added: @names', array(
          '@count' => 1,
          '@names' => 'comment_new'
        )));

    $sensor_config_all = monitoring_sensor_manager()->getAllSensorConfig();
    unset($sensor_config_all['comment_new']);
    $this->assertEqual(SafeMarkup::format($log[1]->message, unserialize($log[1]->variables)),
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
    $this->assertEqual(count($this->loadWatchdog()), 2);

    // Install the comment module to test the correct procedure of removing
    // sensors.
    $this->installModules(array('comment'));
    monitoring_sensor_manager()->enableSensor('comment_new');

    // Now we should be back to normal.
    $result = $this->runSensor('monitoring_disappeared_sensors');
    $this->assertTrue($result->isOk());
    $this->assertEqual(count($this->loadWatchdog()), 2);

    // Do the correct procedure to remove a sensor - first disable the sensor
    // and then uninstall the comment module.
    monitoring_sensor_manager()->disableSensor('comment_new');
    $this->uninstallModules(array('comment'));

    // The sensor should not report any problem this time.
    $result = $this->runSensor('monitoring_disappeared_sensors');
    $this->assertTrue($result->isOk());
    $log = $this->loadWatchdog();
    $this->assertEqual(count($log), 3, 'Removal of comment_new sensor should be logged.');
    $this->assertEqual(SafeMarkup::format($log[2]->message, unserialize($log[2]->variables)),
      SafeMarkup::format('@count new sensor/s removed: @names', array(
          '@count' => 1,
          '@names' => 'comment_new'
        )));
  }

  /**
   * Tests enabled modules sensor.
   *
   * We use separate test method as we need to enable/disable modules.
   *
   * @see EnabledModulesSensorPlugin
   */
  public function testSensorInstalledModulesAPI() {
    // The initial run of the sensor will acknowledge all installed modules as
    // expected and so the status should be OK.
    $result = $this->runSensor('monitoring_enabled_modules');
    $this->assertTrue($result->isOk());

    // Install additional module. As the setting "allow_additional" is not
    // enabled by default this should result in sensor escalation to critical.
    $this->installModules(array('contact'));
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
    $this->uninstallModules(array('contact'));
    $result = $this->runSensor('monitoring_enabled_modules');
    $this->assertTrue($result->isCritical());
    $this->assertEqual($result->getMessage(), '1 modules delta, expected 0, Following modules are expected to be installed: Contact (contact)');
    $this->assertEqual($result->getValue(), 1);
  }


  /**
   * Tests the entity aggregator.
   *
   * @see EntityAggregatorSensorPlugin
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
      'bundle' => $type2->id(),
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
      'bundle' => $type2->id(),
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
      'type' => $type2->id(),
      'term_reference' => array(array('target_id' => $term1->id())),
    ));

    // Create node that only references both terms.
    $this->drupalCreateNode(array(
      'created' => REQUEST_TIME,
      'type' => $type2->id(),
      'term_reference' => array(
        array('target_id' => $term1->id()),
        array('target_id' => $term2->id()),
      ),
    ));

    // Create a third node that references both terms but in different fields.
    $this->drupalCreateNode(array(
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
