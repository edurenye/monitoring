<?php
/**
 * @file
 * Monitoring munin tests.
 */

namespace Drupal\monitoring_munin\Tests;

use Drupal\monitoring\Tests\MonitoringTestBase;

/**
 * Class MonitoringAPITest
 */
class MonitoringMuninTest extends MonitoringTestBase {

  public static $modules = array('monitoring', 'monitoring_test', 'monitoring_munin', 'dblog');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Monitoring Munin',
      'description' => 'Monitoring Munin tests.',
      'group' => 'Monitoring',
    );
  }

  /**
   * Tests the multigraph API/CRUD
   */
  function testMultigraphsAPI() {
    // Test adding a multigraph.
    monitoring_munin_multigraph_save('test multigraph', 'test unit');
    $multigraphs = monitoring_munin_multigraphs();
    $this->assertEqual($multigraphs['test multigraph']['title'], 'test multigraph');
    $this->assertEqual($multigraphs['test multigraph']['vlabel'], 'test unit');

    // Add multigraph to a sensor.
    $settings = monitoring_sensor_settings_get('dblog_404');
    $settings['munin']['multigraphs'][] = 'test multigraph';
    monitoring_sensor_settings_save('dblog_404', $settings);

    // Deleting the multigraph must remove it form sensors settings as well.
    monitoring_munin_multigraph_delete('test multigraph');
    monitoring_sensor_manager()->resetCache();
    $info = $this->sensorManager->getSensorConfigByName('dblog_404');
    $munin_settings = $info->getSetting('munin');
    $this->assertTrue(!in_array('test multigraph', $munin_settings['multigraphs']));
  }

  /**
   * Tests Munin default settings enabled after module installation.
   *
   * @todo - enable once we are done with port to D8.
   */
  function dtestMultigraphsDefaultSettings() {
    // When enabled we should have Watchdog and User activity multigraphs
    // created.
    $multigraphs = monitoring_munin_multigraphs();
    $this->assertEqual($multigraphs, array(
      'Watchdog' => array(
        'title' => 'Watchdog',
        'vlabel' => 'Watchdog items',
      ),
      'User activity' => array(
        'title' => 'User activity',
        'vlabel' => 'Users',
      ),
    ));

    foreach (monitoring_sensor_config_by_categories() as $category => $sensors) {
      if (!isset($multigraphs[$category])) {
        continue;
      }
      /** @var \Drupal\monitoring\Sensor\SensorConfig $sensor */
      foreach ($sensors as $sensor) {
        $munin_settings = $sensor->getSetting('munin');
        $this->assertTrue($munin_settings['munin_enabled']);
        $this->assertEqual($munin_settings['multigraphs'], array($category));
      }
    }

  }

  //@todo - finalise tests below.

  /**
   * Tests the config output used by Munin to define graphs.
   */
//  function testGraphConfigOutput() {
//
//  }

  /**
   * Tests the results provided to Munin.
   */
//  function testGraphResults() {
//
//  }

}
