<?php
/**
 * @file
 * Contains \Drupal\monitoring_demo\Controller\FrontPage.
 */

namespace Drupal\monitoring_demo\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Simple front page controller for the monitoring_demo module.
 */
class FrontPage extends ControllerBase {

  public function content() {
    return array(
      'intro' => array(
        '#markup' => '<p>' . t('Welcome to the Monitoring demo installation. Content and log messages (including dummy errors) have been generated automatically to demonstrate different sensors and their escalation.'),
      ),
      'report' => array(
        '#type' => 'item',
        '#title' => l(t('Monitoring sensors overview'), 'admin/reports/monitoring'),
        '#description' => t('Basic dashboard showing the sensor list with their status and information.'),
      ),
      'configuration' => array(
        '#type' => 'item',
        '#title' => l(t('Monitoring sensors settings'), 'admin/config/system/monitoring'),
        '#description' => t('Monitoring sensors configuration page. See this page for the complete list of the available sensors.'),
      ),
      'sensor_enabled_modules' => array(
        '#type' => 'item',
        '#title' => t('Sensor example: "Enabled modules"'),
        '#description' => t('Monitors which modules are supposed to be enabled. In case there is a needed module disabled or excess module enabled you will be noticed.'),
        'list' => array(
          '#theme' => 'item_list',
          '#items' => array(
            t('<a href="@url">Configure</a> the module by submitting the default settings.', array('@url' => url('admin/config/system/monitoring/sensors/enabled_modules'))),
            t('<a href="@url">Disable</a> Dashboard module and enable Book module.', array('@url' => url('admin/modules'))),
            t('Visit the <a href="@url">sensors overview page</a> to see the reported issue.', array('@url' => url('admin/reports/monitoring/sensors'))),
          )
        ),
      ),
      'sensor_disappeared_sensors' => array(
        '#type' => 'item',
        '#title' => t('Sensor example: "Disappeared sensors"'),
        '#description' => t('Additionally to disabling modules, configuration changes like removing content types or search API indexes could lead to sensors that silently disappear.'),
        'list' => array(
          '#theme' => 'item_list',
          '#items' => array(
            t('<a href="@url">Uninstall</a> the Database logging module what will make all the watchdog related sensors disappear.',
              array('@url' => url('admin/modules/uninstall'))),
            t('Visit the <a href="@url">sensors overview page</a> to see the sensor reporting disappeared sensors.', array('@url' => url('admin/reports/monitoring'))),
          )
        ),
      ),
      'integrations' => array(
        '#type' => 'item',
        '#title' => t('Integrations'),
        'list' => array(
          '#theme' => 'item_list',
          '#items' => array(
            t('Drush integration - open up your console and type in # drush monitoring-info or # drush monitoring-run. See the drush help for more info and commands.'),
            t('REST resource for both the info about sensors and running the sensors via the service. Open up your REST client and visit /monitoring-sensor-info/{sensor_name} and /monitoring-sensor-result/{sensor_name}'),
          )
        ),
      ),
    );
  }
}
