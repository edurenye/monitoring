<?php
/**
 * @file
 * Monitoring API documentation.
 */

use Drupal\monitoring\Result\SensorResultInterface;


/**
 * Allows to alter sensor links on the sensor overview page.
 *
 * @param array $links
 *   Links to be altered.
 * @param \Drupal\monitoring\Entity\SensorConfig $sensor_config
 *   Sensor config object of a sensor for which links are being altered.
 *
 * @see monitoring_reports_sensors_overview()
 */
function hook_monitoring_sensor_links_alter(&$links, \Drupal\monitoring\Entity\SensorConfig $sensor_config) {

}
