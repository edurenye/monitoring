<?php
/**
 * @file
 * Contains \Drupal\monitoring\Sensor\SensorBase.
 */

namespace Drupal\monitoring\Sensor;

use Drupal\monitoring\Entity\SensorConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract SensorInterface implementation with common behaviour and will be extended by
 * sensor plugins.
 *
 * @todo more
 */
abstract class SensorBase implements SensorInterface {

  /**
   * Current sensor config object.
   *
   * @var SensorConfig
   */
  protected $sensorConfig;
  protected $services = array();

  /**
   * The plugin_id.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The plugin implementation definition.
   *
   * @var array
   */
  protected $pluginDefinition;

  /**
   * Instantiates a sensor object.
   *
   * @param SensorConfig $sensor_config
   *   Sensor config object.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  function __construct(SensorConfig $sensor_config, $plugin_id, $plugin_definition) {
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->sensorConfig = $sensor_config;
  }

  /**
   * {@inheritdoc}
   */
  public function addService($id, $service) {
    $this->services[$id] = $service;
  }

  /**
   * {@inheritdoc}
   *
   * @todo: Replace with injection
   */
  public function getService($id) {
    return \Drupal::service($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getSensorName() {
    return $this->sensorConfig->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return (boolean) $this->sensorConfig->isEnabled();
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, SensorConfig $sensor_config, $plugin_id, $plugin_definition) {
    return new static(
      $sensor_config,
      $plugin_id,
      $plugin_definition
    );
  }
}
