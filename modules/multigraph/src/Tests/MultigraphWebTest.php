<?php
/**
 * @file
 * Contains Drupal\monitoring_multigraph\Tests\MultigraphWebTest
 */

namespace Drupal\monitoring_multigraph\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests the Multigraph forms (add/edit/delete).
 *
 * @group monitoring
 */
class MultigraphWebTest extends WebTestBase {

  /**
   * User object.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * String that is appended to values for testing.
   *
   * @var string
   */
  protected $appendString = ' (test)';

  /**
   * Label of pre-installed multigraph.
   *
   * @var string
   */
  protected $preinstalledMultigraphLabel = 'Watchdog severe entries';

  /**
   * Description of pre-installed multigraph.
   *
   * @var string
   */
  protected $preinstalledMultigraphDescription = 'Watchdog entries with severity Warning or higher';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'dblog',
    'node',
    'monitoring',
    'monitoring_multigraph',
  );

  /**
   * Configures test base and executes test cases.
   */
  public function testMultigraphForm() {
    // Create and log in our user.
    $this->adminUser = $this->drupalCreateUser(array(
      'administer monitoring',
    ));

    $this->drupalLogin($this->adminUser);

    $this->doTestMultigraphAdd();
    $this->doTestMultigraphEdit();
    $this->doTestMultigraphDelete();
  }

  /**
   * Create multigraph.
   */
  public function doTestMultigraphAdd() {
    // Add a few sensors.
    $values = array(
      'label' => $this->randomString(),
      'id' => 'multigraph_123',
      'description' => $this->randomString(),
      'sensor_add[sensor_add_select]' => 'dblog_404',
    );
    $this->drupalPostForm('admin/config/system/monitoring/multigraphs/add', $values, t('Add'));
    $this->assertText(t('Sensor "Page not found errors" added. You have unsaved changes.'));

    $this->drupalPostForm(NULL, array(
      'sensor_add[sensor_add_select]' => 'monitoring_disappeared_sensors',
    ), t('Add'));
    $this->assertText(t('Sensor "Disappeared sensors" added. You have unsaved changes.'));

    $this->drupalPostForm(NULL, array(
      'sensor_add[sensor_add_select]' => 'user_successful_logins',
    ), t('Add'));
    $this->assertText(t('Sensor "Successful user logins" added. You have unsaved changes.'));

    // And last but not least, change all sensor label values and save form.
    $this->drupalPostForm(NULL, array(
      'sensor_add[sensors][dblog_404][label]' => 'Page not found errors' . $this->appendString,
      'sensor_add[sensors][monitoring_disappeared_sensors][label]' => 'Disappeared sensors' . $this->appendString,
      'sensor_add[sensors][user_successful_logins][label]' => 'Successful user logins' . $this->appendString,
    ), t('Save'));
    $this->assertText(t('Multigraph settings saved.'));
    $this->assertText(t('Page not found errors@appendString, Disappeared sensors@appendString, Successful user logins@appendString', array('@appendString' => $this->appendString)));
  }

  /**
   * Edit multigraph (tests all changeable input fields).
   */
  public function doTestMultigraphEdit() {
    // Go to multigraph overview and test editing pre-installed multigraph.
    $this->drupalGet('admin/config/system/monitoring/multigraphs');
    // Check label, description and sensors (before editing).
    $this->assertText($this->preinstalledMultigraphLabel);
    $this->assertText($this->preinstalledMultigraphDescription);
    $this->assertText('404, Alert, Critical, Emergency, Error');

    // Edit.
    $this->drupalGet('admin/config/system/monitoring/multigraphs/watchdog_severe_entries');
    $this->assertText('Edit Multigraph');

    // Change label, description and add a sensor.
    $values = array(
      'label' => $this->preinstalledMultigraphLabel . $this->appendString,
      'description' => $this->preinstalledMultigraphDescription . $this->appendString,
      'sensor_add[sensor_add_select]' => 'user_successful_logins',
    );
    $this->drupalPostForm(NULL, $values, t('Add'));
    $this->assertText('Sensor "Successful user logins" added. You have unsaved changes.');

    // Remove a sensor.
    // (drupalPostAjaxForm() lets us target the button precisely.)
    $this->drupalPostAjaxForm(NULL, array(), array('remove_dblog_404' => t('Remove')));
    $this->assertText(t('Sensor "Page not found errors" removed.  You have unsaved changes.'));
    $this->drupalPostForm(NULL, array(), t('Save'));

    // Change weights and save form.
    $this->drupalPostForm('admin/config/system/monitoring/multigraphs/watchdog_severe_entries', array(
      'sensor_add[sensors][user_successful_logins][weight]' => -2,
      'sensor_add[sensors][dblog_event_severity_error][weight]' => -1,
      'sensor_add[sensors][dblog_event_severity_critical][weight]' => 0,
      'sensor_add[sensors][dblog_event_severity_emergency][weight]' => 1,
      'sensor_add[sensors][dblog_event_severity_alert][weight]' => 2,
    ), t('Save'));
    $this->assertText(t('Multigraph settings saved.'));

    // Go back to multigraph overview and check changed values.
    $this->drupalGet('admin/config/system/monitoring/multigraphs');
    $this->assertText($this->preinstalledMultigraphLabel . $this->appendString);
    $this->assertText($this->preinstalledMultigraphDescription . $this->appendString);
    $this->assertText('Successful user logins, Error, Critical, Emergency, Alert');
  }

  /**
   * Delete multigraph.
   */
  public function doTestMultigraphDelete() {
    // Go to multigraph overview and check for pre-installed multigraph.
    $this->drupalGet('admin/config/system/monitoring/multigraphs');
    // Check label and description (before deleting).
    $this->assertText($this->preinstalledMultigraphLabel);
    $this->assertText($this->preinstalledMultigraphDescription);

    // Delete.
    $this->drupalPostForm('admin/config/system/monitoring/multigraphs/watchdog_severe_entries/delete', array(), t('Delete'));
    $this->assertText('The ' . $this->preinstalledMultigraphLabel . $this->appendString . ' multigraph has been deleted');

    // Go back to multigraph overview and check that multigraph is deleted.
    $this->drupalGet('admin/config/system/monitoring/multigraphs');
    $this->assertNoText($this->preinstalledMultigraphLabel);
    $this->assertNoText($this->preinstalledMultigraphDescription);
  }
}
