<?php
/**
 * @file
 * Contains \Drupal\monitoring\Tests\MonitoringTestBase.
 */

namespace Drupal\monitoring\Tests;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\simpletest\WebTestBase;

/**
 * Base class for all monitoring web tests.
 */
abstract class MonitoringTestBase extends WebTestBase {

  public static $modules = ['block', 'monitoring', 'monitoring_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // @todo Remove when this issue is fixed: https://www.drupal.org/node/2611082
    date_default_timezone_set('Australia/Sydney');

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');
    if (!\Drupal::moduleHandler()->moduleExists('monitoring')) {
      throw new \Exception("Failed to install modules, aborting test");
    }
  }

  /**
   * Executes a sensor and returns the result.
   *
   * @param string $sensor_name
   *   Name of the sensor to execute.
   *
   * @return \Drupal\monitoring\Result\SensorResultInterface
   *   The sensor result.
   */
  protected function runSensor($sensor_name) {
    // Make sure the sensor is enabled.
    monitoring_sensor_manager()->enableSensor($sensor_name);
    return monitoring_sensor_run($sensor_name, TRUE, TRUE);
  }

  /**
   * Install modules and fix test container.
   *
   * @param string[] $module_list
   *   An array of module names.
   * @param bool $enable_dependencies
   *   (optional) If TRUE, dependencies will automatically be installed in the
   *   correct order. This incurs a significant performance cost, so use FALSE
   *   if you know $module_list is already complete.
   *
   * @return bool
   *   FALSE if one or more dependencies are missing, TRUE otherwise.
   *
   * @see uninstallModules()
   * @see \Drupal\Core\Extension\ModuleInstallerInterface::install()
   */
  protected function installModules(array $module_list, $enable_dependencies = TRUE) {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_handler */
    $module_handler = \Drupal::service('module_installer');

    // Install the modules requested.
    $return = $module_handler->install($module_list, $enable_dependencies);

    // The container is rebuilt, thus reassign it.
    $this->container = \Drupal::getContainer();

    return $return;
  }



  /**
   * Uninstall modules and fix test container.
   *
   * @param string[] $module_list
   *   The modules to uninstall.
   * @param bool $uninstall_dependents
   *   (optional) If TRUE, dependent modules will automatically be uninstalled
   *   in the correct order. This incurs a significant performance cost, so use
   *   FALSE if you know $module_list is already complete.
   *
   * @return bool
   *   FALSE if one or more dependencies are missing, TRUE otherwise.
   *
   * @see installModules()
   * @see \Drupal\Core\Extension\ModuleInstallerInterface::uninstall()
   */
  protected function uninstallModules(array $module_list, $uninstall_dependents = TRUE) {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_handler */
    $module_handler = \Drupal::service('module_installer');

    // Install the modules requested.
    $return = $module_handler->uninstall($module_list, $uninstall_dependents);

    // The container is rebuilt, thus reassign it.
    $this->container = \Drupal::getContainer();

    return $return;
  }
}
