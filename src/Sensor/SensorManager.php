<?php
/**
 * @file
 * Contains \Drupal\monitoring\Sensor\SensorManager.
 */

namespace Drupal\monitoring\Sensor;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\String;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\monitoring\SensorRunner;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\monitoring\Entity\SensorConfig;

/**
 * Manages sensor definitions and settings.
 *
 * Provides list of enabled sensors.
 * Sensors can be listed by category.
 *
 * Maintains a (non persistent) info cache.
 * Enables and disables sensors.
 *
 */
class SensorManager extends DefaultPluginManager {

  /**
   * List of sensor definitions.
   *
   * @var \Drupal\monitoring\Entity\SensorConfig[]
   */
  protected $sensor_config;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructs a sensor manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config) {
    parent::__construct('Plugin/monitoring/Sensor', $namespaces, $module_handler, '\Drupal\monitoring\Sensor\SensorInterface', 'Drupal\monitoring\Annotation\Sensor');
    $this->alterInfo('block');
    $this->setCacheBackend($cache_backend, 'monitoring_sensor_plugins');
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = array()) {
    // Configuration contains sensor_info object. Extracting
    // it to use for sensor object creation.
    $sensor_config = $configuration['sensor_info'];
    $definition = $this->getDefinition($plugin_id);
    // Sensor class from the sensor definition.
    $class = $definition['class'];
    // Creating instance of the sensor. Refer Sensor.php for arguments.
    return $class::create(\Drupal::getContainer(), $sensor_config, $plugin_id, $definition);
  }

  /**
   * Returns monitoring sensor config.
   *
   * @return \Drupal\monitoring\Entity\SensorConfig[]
   *   List of SensorConfig instances.
   */
  public function getAllSensorConfig() {
    $sensors = SensorConfig::loadMultiple();

    // Sort the sensors by category and label.
    uasort($sensors, "\Drupal\monitoring\Entity\SensorConfig::sort");

    return $sensors;
  }

  /**
   * Returns monitoring sensor config for enabled sensors.
   *
   * @return \Drupal\monitoring\Entity\SensorConfig[]
   *   List of SensorConfig instances.
   */
  public function getEnabledSensorConfig() {
    $enabled_sensors = array();
    foreach ($this->getAllSensorConfig() as $sensor_config) {
      if ($sensor_config->isEnabled()) {
        $enabled_sensors[$sensor_config->getName()] = $sensor_config;
      }
    }
    return $enabled_sensors;
  }

  /**
   * Returns monitoring sensor config for a given sensor.
   *
   * @param string $sensor_name
   *   Sensor id.
   *
   * @return \Drupal\monitoring\Entity\SensorConfig
   *   A single SensorConfig instance.
   *
   * @throws \Drupal\monitoring\Sensor\NonExistingSensorException
   *   Thrown if the requested sensor does not exist.
   */
  public function getSensorConfigByName($sensor_name) {
    $sensor_config_all = $this->getAllSensorConfig();
    if (isset($sensor_config_all[$sensor_name])) {
      return $sensor_config_all[$sensor_name];
    }
    throw new NonExistingSensorException(String::format('Sensor @sensor_name does not exist', array('@sensor_name' => $sensor_name)));
  }

  /**
   * Gets sensor config grouped by categories.
   *
   * @todo: The enabled flag is strange, FALSE should return all?
   *
   * @param bool $enabled
   *   Sensor isEnabled flag.
   *
   * @return \Drupal\monitoring\Entity\SensorConfig[]
   *   Sensor config.
   */
  public function getSensorConfigByCategories($enabled = TRUE) {
    $config_by_categories = array();
    foreach ($this->getAllSensorConfig() as $sensor_name => $sensor_config) {
      if ($sensor_config->isEnabled() != $enabled) {
        continue;
      }

      $config_by_categories[$sensor_config->getCategory()][$sensor_name] = $sensor_config;
    }

    return $config_by_categories;
  }

  /**
   * Reset the static cache.
   */
  public function resetCache() {
    $this->sensor_config = array();
  }

  /**
   * Enable a sensor.
   *
   * Checks if the sensor is enabled and enables it if not.
   *
   * @param string $sensor_name
   *   Sensor name to be enabled.
   *
   * @throws \Drupal\monitoring\Sensor\NonExistingSensorException
   *   Thrown if the requested sensor does not exist.
   */
  public function enableSensor($sensor_name) {
    $sensor_config = $this->getSensorConfigByName($sensor_name);
    if (!$sensor_config->isEnabled()) {
      $sensor_config->status = TRUE;
      $sensor_config->save();

      $available_sensors = \Drupal::state()->get('monitoring.available_sensors', array());

      if (!isset($available_sensors[$sensor_name])) {
        // Use the watchdog message as the disappeared sensor does when new
        // sensors are detected.
        \Drupal::logger('monitoring')->notice('@count new sensor/s added: @names',
          array('@count' => 1, '@names' => $sensor_name));
      }

      $available_sensors[$sensor_name]['enabled'] = TRUE;
      $available_sensors[$sensor_name]['name'] = $sensor_name;
      \Drupal::state()->set('monitoring.available_sensors', $available_sensors);
    }
  }

  /**
   * Disable a sensor.
   *
   * Checks if the sensor is enabled and if so it will disable it and remove
   * from the active sensor list.
   *
   * @param string $sensor_name
   *   Sensor name to be disabled.
   *
   * @throws \Drupal\monitoring\Sensor\NonExistingSensorException
   *   Thrown if the requested sensor does not exist.
   */
  public function disableSensor($sensor_name) {
    $sensor_config = $this->getSensorConfigByName($sensor_name);
    if ($sensor_config->isEnabled()) {
      $sensor_config->status = FALSE;
      $sensor_config->save();
      $available_sensors = \Drupal::state()->get('monitoring.available_sensors', array());
      $available_sensors[$sensor_name]['enabled'] = FALSE;
      $available_sensors[$sensor_name]['name'] = $sensor_name;
      \Drupal::state()->set('monitoring.available_sensors', $available_sensors);
    }
  }

  /**
   * Returns if an array is flat.
   *
   * @param array $array
   *   The array to check.
   *
   * @return bool
   *   TRUE if the array has no values that are arrays again.
   */
  protected function isFlatArray(array $array) {
    foreach ($array as $value) {
      if (is_array($value)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
