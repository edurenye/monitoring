<?php
/**
 * @file
 * Contains \Drupal\monitoring\SensorPlugin\ExtendedInfoSensorPluginInterface.
 */

namespace Drupal\monitoring\SensorPlugin;

use Drupal\monitoring\Result\SensorResultInterface;

/**
 * Interface for a sensor with extended info.
 *
 * Implemented by sensors with verbose information.
 */
interface ExtendedInfoSensorPluginInterface {

  /**
   * Provide additional info about sensor call.
   *
   * @param SensorResultInterface $result
   *   Sensor result.
   *
   * @return array
   *   Sensor call verbose info as render array.
   */
  function resultVerbose(SensorResultInterface $result);

}
