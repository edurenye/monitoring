<?php
/**
 * @file
 * Contains \Drupal\monitoring\Tests\MonitoringViewDisplayTest.
 */

namespace Drupal\monitoring\Tests;

/**
 * Tests the view display sensor.
 */
class MonitoringViewDisplayTest extends MonitoringTestBase {

  public static $modules = array('views');

  public static function getInfo() {
    return array(
      'name' => 'Monitoring View Display',
      'description' => 'Monitoring view display test.',
      'group' => 'Monitoring',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
  }

  /**
   * Tests the view display sensor.
   *
   * @see ViewDisplayAggregatorSensorPlugin
   */
  public function testViewDisplaySensor() {
    $account = $this->drupalCreateUser(array('administer monitoring', 'monitoring reports'));
    $this->drupalLogin($account);

    // Add sensor type views display aggregator.
    $this->drupalGet('admin/config/system/monitoring/sensors/add');
    $this->drupalPostForm(NULL, array(
      'label' => 'All users',
      'id' => 'view_user_count',
      'plugin_id' => 'view_display_aggregator',
    ), t('Select sensor'));
    // Select view and display and save.
    $this->assertText('Sensor plugin settings');
    $this->drupalPostForm(NULL, array(
      'description' => 'Count all users through the users view.',
      'value_type' => 'number',
      'value_label' => 'Users',
      'caching_time' => 0,
      'settings[view]' => 'user_admin_people',
    ), t('Select view'));
    $this->drupalPostForm(NULL, array(
      'settings[display]' => 'default',
    ), t('Save'));
    $this->assertText('Sensor settings saved.');

    // Edit and check selection.
    $this->drupalGet('admin/config/system/monitoring/sensors/view_user_count');
    $this->assertOptionSelected('edit-settings-view', 'user_admin_people');
    $this->assertOptionSelected('edit-settings-display', 'default');

    // Call sensor and verify status and message.
    $result = $this->runSensor('view_user_count');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), '2 users');

    // Create an additional user.
    $this->drupalCreateUser();

    // Call sensor and verify status and message.
    $result = $this->runSensor('view_user_count');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), '3 users');
  }

}
