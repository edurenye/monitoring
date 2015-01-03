<?php
/**
 * @file
 * Contains \Drupal\monitoring\Tests\MonitoringCaptchaTest.
 */

namespace Drupal\monitoring\Tests;

/**
 * Tests the captcha failed attempts sensor.
 */
class MonitoringCaptchaTest extends MonitoringTestBase {

 /**
  * Disabled config schema checking temporarily until all errors are resolved.
  */
 protected $strictConfigSchema = FALSE;

  public static $modules = array('captcha');

  public static function getInfo() {
    return array(
      'name' => 'Monitoring Captcha',
      'description' => 'Monitoring captcha fails test.',
      'group' => 'Monitoring',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Add captcha.inc file.
    module_load_include('inc', 'captcha');
  }

  /**
   * Tests the captcha failed attempts sensor.
   */
  public function testCaptchaSensor() {

    // Create user and test log in without CAPTCHA.
    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);
    // Log out again.
    $this->drupalLogout();

    // Set a CAPTCHA on login form.
    captcha_set_form_id_setting('user_login_form', 'captcha/Math');

    // Try to log in, with invalid captcha answer which should fail.
    $edit = array(
      'name' => $user->getUsername(),
      'pass' => $user->pass_raw,
      'captcha_response' => '?',
    );
    $this->drupalPostForm('user', $edit, t('Log in'));

    // Run sensor and get the message.
    $message = $this->runSensor('captcha_failed_count')->getMessage();

    // Assert the number of failed attempts.
    $this->assertEqual($message, '1 attempt(s)');

    // Assert the total number of entries in captcha_sessions table.
    $this->assertEqual(db_query('SELECT COUNT (*) FROM {captcha_sessions}')->fetchField(), 3);
  }

}
